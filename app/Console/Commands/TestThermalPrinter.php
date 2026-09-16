<?php

namespace App\Console\Commands;

use App\Services\Printing\ThermalPrinterService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Hardware connectivity check for the network kitchen/receipt printer —
 * sends a short test slip so wiring/IP/port can be verified without going
 * through the order-printing flow.
 */
class TestThermalPrinter extends Command
{
    protected $signature = 'printer:test {--host= : Override the configured printer host} {--port= : Override the configured printer port}';

    protected $description = 'Send a test slip to the configured network thermal (ESC/POS) printer.';

    public function handle(): int
    {
        $hosts = $this->option('host')
            ? [$this->option('host')]
            : config('printing.thermal.hosts');
        $port = (int) ($this->option('port') ?: config('printing.thermal.port'));

        $this->info('Trying '.implode(', ', $hosts)." on port {$port} ...");

        try {
            (new ThermalPrinterService($hosts, $port))->printTest();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent to printer.');

        return self::SUCCESS;
    }
}
