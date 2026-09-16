<?php

namespace App\Console\Commands;

use App\Services\Printing\SlipImageRenderer;
use App\Services\Printing\ThermalPrinterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Long-running process for a PC inside the resort's own network (same LAN
 * as the thermal printer). Production runs on a Hostinger VPS with no
 * route to the printer's private IP, so print jobs can't be pushed to it
 * directly — this polls the production API for pending jobs instead, and
 * prints each one locally. See config/printing.php's "Printer Bridge"
 * block for why polling was chosen over a live Reverb broadcast (a job
 * broadcast while this process is offline would be lost forever; a job
 * sitting in the queue table just waits).
 *
 * Run this as a persistent background process on that PC (e.g. via NSSM
 * as a Windows service, or Task Scheduler "run at startup" + auto-restart)
 * — `php artisan printer:bridge`.
 */
class ThermalPrinterBridge extends Command
{
    protected $signature = 'printer:bridge {--once : Poll a single time and exit, instead of looping forever}';

    protected $description = 'Poll the production server for queued kitchen-slip print jobs and print them on the local network thermal printer.';

    public function handle(): int
    {
        $apiUrl = rtrim((string) config('printing.bridge_api_url'), '/');
        $token = config('printing.bridge_token');

        if (! $apiUrl || ! $token) {
            $this->error('PRINTER_BRIDGE_API_URL and PRINTER_BRIDGE_TOKEN must both be set in .env for this machine.');

            return self::FAILURE;
        }

        $printer = ThermalPrinterService::fromConfig();

        $this->info("Printer bridge started. Polling {$apiUrl} every 3s. Ctrl+C to stop.");

        do {
            $this->poll($apiUrl, $token, $printer);
            sleep(3);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function poll(string $apiUrl, string $token, ThermalPrinterService $printer): void
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$apiUrl}/api/printer-jobs")
                ->throw();
        } catch (Throwable $e) {
            $this->warn('Could not reach production server: '.$e->getMessage());

            return;
        }

        foreach ($response->json('jobs', []) as $job) {
            $this->printJob($apiUrl, $token, $printer, $job);
        }
    }

    private function printJob(string $apiUrl, string $token, ThermalPrinterService $printer, array $job): void
    {
        try {
            match ($job['type']) {
                'kitchen_slip' => $this->printKitchenSlip($printer, $job['payload']),
                default => throw new \RuntimeException("Unknown printer job type: {$job['type']}"),
            };

            $this->ack($apiUrl, $token, $job['id'], 'printed');
            $this->info("Printed job #{$job['id']} ({$job['type']}).");
        } catch (Throwable $e) {
            $this->ack($apiUrl, $token, $job['id'], 'failed', $e->getMessage());
            $this->error("Job #{$job['id']} failed: {$e->getMessage()}");
        }
    }

    /**
     * Prefer the screenshot, which comes out identical to the browser Print
     * button. If rendering it fails — Chrome missing, updated, or just slow
     * — fall back to the ESC/POS text layout rather than dropping the slip:
     * an unprinted ticket means food that never gets cooked.
     */
    private function printKitchenSlip(ThermalPrinterService $printer, array $payload): void
    {
        if (! empty($payload['html'])) {
            $renderer = null;
            $png = null;

            try {
                $renderer = SlipImageRenderer::fromConfig();
                $png = $renderer->render($payload['html']);
                $printer->printImage($png);

                return;
            } catch (Throwable $e) {
                $this->warn('Image render failed, falling back to text layout: '.$e->getMessage());
            } finally {
                if ($renderer && $png) {
                    $renderer->cleanUp($png);
                }
            }
        }

        $printer->printKitchenSlip($payload);
    }

    private function ack(string $apiUrl, string $token, int $jobId, string $status, ?string $errorMessage = null): void
    {
        try {
            Http::withToken($token)->timeout(10)->post("{$apiUrl}/api/printer-jobs/{$jobId}/ack", [
                'status' => $status,
                'error_message' => $errorMessage,
            ])->throw();
        } catch (Throwable $e) {
            $this->warn("Could not ack job #{$jobId}: {$e->getMessage()}");
        }
    }
}
