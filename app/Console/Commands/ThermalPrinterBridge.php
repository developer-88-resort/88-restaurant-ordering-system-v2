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
    /** Job ids this machine has already put on paper — see printedJobs(). */
    private const REMEMBER_JOBS = 500;

    /** @var array<int, int> */
    private array $printedJobs = [];

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
        $this->printedJobs = $this->loadPrintedJobs();

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
        // Already on paper: the server never got our acknowledgement and is
        // offering it again. Say so once more — don't print a second copy.
        if (in_array((int) $job['id'], $this->printedJobs, true)) {
            $this->ack($apiUrl, $token, $job['id'], 'printed');
            $this->warn("Job #{$job['id']} was already printed here — re-acknowledged, not reprinted.");

            return;
        }

        try {
            match ($job['type']) {
                'kitchen_slip' => $this->printKitchenSlip($printer, $job['payload']),
                default => throw new \RuntimeException("Unknown printer job type: {$job['type']}"),
            };

            $this->rememberPrinted((int) $job['id']);
            $this->ack($apiUrl, $token, $job['id'], 'printed');
            $copies = $this->copies($job['payload'] ?? []);
            $this->info("Printed job #{$job['id']} ({$job['type']})".($copies > 1 ? ", {$copies} copies" : '').'.');
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
                $printer->printImage($png, $this->copies($payload), $this->pauseSeconds($payload));

                return;
            } catch (Throwable $e) {
                $this->warn('Image render failed, falling back to text layout: '.$e->getMessage());
            } finally {
                if ($renderer && $png) {
                    $renderer->cleanUp($png);
                }
            }
        }

        $printer->printKitchenSlip($payload, $this->copies($payload), $this->pauseSeconds($payload));
    }

    /**
     * How many slips this press should put out, and how long to rest between
     * them. The server works both out per order (an advance order gets three)
     * and sends them with the job; the config values are only a fallback for
     * a job queued by an older release.
     *
     * @param  array<string, mixed>  $payload
     */
    private function copies(array $payload): int
    {
        return max(1, (int) ($payload['copies'] ?? config('printing.kitchen_slip_copies', 1)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pauseSeconds(array $payload): int
    {
        return max(0, (int) ($payload['copy_pause_seconds'] ?? config('printing.copy_pause_seconds', 3)));
    }

    /**
     * Acknowledging matters as much as printing: a slip whose "printed" never
     * lands is offered again once the claim goes stale, so this retries
     * before giving up (printedJobs() is the backstop if it still fails).
     */
    private function ack(string $apiUrl, string $token, int $jobId, string $status, ?string $errorMessage = null): void
    {
        foreach ([0, 2, 5] as $attempt => $waitSeconds) {
            if ($waitSeconds > 0) {
                sleep($waitSeconds);
            }

            try {
                Http::withToken($token)->timeout(10)->post("{$apiUrl}/api/printer-jobs/{$jobId}/ack", [
                    'status' => $status,
                    'error_message' => $errorMessage,
                ])->throw();

                return;
            } catch (Throwable $e) {
                $this->warn("Could not ack job #{$jobId} (try ".($attempt + 1)."): {$e->getMessage()}");
            }
        }
    }

    /**
     * The ids printed by this machine, kept in a small file next to the app
     * so a restart doesn't forget them and reprint a re-offered slip.
     *
     * @return array<int, int>
     */
    private function loadPrintedJobs(): array
    {
        $path = $this->printedJobsPath();

        if (! is_file($path)) {
            return [];
        }

        $ids = json_decode((string) file_get_contents($path), true);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    private function rememberPrinted(int $jobId): void
    {
        $this->printedJobs[] = $jobId;
        $this->printedJobs = array_slice(array_unique($this->printedJobs), -self::REMEMBER_JOBS);

        @file_put_contents($this->printedJobsPath(), json_encode(array_values($this->printedJobs)));
    }

    private function printedJobsPath(): string
    {
        return storage_path('app/printer-bridge-printed-jobs.json');
    }
}
