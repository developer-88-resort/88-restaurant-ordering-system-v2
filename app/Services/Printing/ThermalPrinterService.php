<?php

namespace App\Services\Printing;

use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper around mike42/escpos-php for the resort's network kitchen
 * printer. Connects fresh for every print — these printers don't hold a
 * persistent socket open, and a request-scoped Laravel process has no
 * business keeping one alive between requests either.
 *
 * Takes a LIST of candidate hosts, not one: the printer holds a static IP,
 * and the two sites it moves between are on different subnets (192.168.1.x
 * here, 192.168.0.x at the restaurant), so its address legitimately differs
 * by location. Rather than making someone remember to edit .env on every
 * move, each candidate is tried in turn and the first that answers wins.
 */
class ThermalPrinterService
{
    /**
     * Short per-candidate connect timeout. The default would be PHP's
     * default_socket_timeout (60s), which would stall every print at the
     * site where the other subnet's address is the dead one.
     */
    private const CONNECT_TIMEOUT_SECONDS = 3;

    /**
     * @param  list<string>  $hosts
     */
    public function __construct(
        private readonly array $hosts,
        private readonly int $port,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            hosts: config('printing.thermal.hosts'),
            port: (int) config('printing.thermal.port'),
        );
    }

    /**
     * Prints a plain connectivity/alignment test slip. Used by the
     * `printer:test` artisan command to verify hardware wiring.
     */
    public function printTest(): void
    {
        $this->withPrinter(function (Printer $printer): void {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(config('app.name') . "\n");
            $printer->text("--- PRINT TEST OK ---\n");
            $printer->text(now()->format('Y-m-d H:i:s') . "\n");
            $printer->feed(2);
            $printer->cut();
        });
    }

    /**
     * Prints an already-rendered PNG as a bitmap. This is how Direct Print
     * matches the browser Print button exactly — the same stylesheet draws
     * both, and the printer reproduces the picture rather than an ESC/POS
     * approximation of the layout.
     */
    public function printImage(string $pngPath): void
    {
        $image = new SlipEscposImage($pngPath);

        $this->withPrinter(function (Printer $printer) use ($image): void {
            $printer->graphics($image);
            $printer->feed(3);
            $printer->cut();
        });
    }

    /**
     * Renders a payload built by KitchenSlipPayloadBuilder. Deliberately
     * dumb — every piece of business logic (what counts as cancelled, who
     * "Waiter"/"Ordered By" means, translations) was already resolved
     * server-side when the payload was built; this only lays plain text
     * out on the paper.
     *
     */
    public function printKitchenSlip(array $payload): void
    {
        $this->withPrinter(function (Printer $printer) use ($payload): void {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($payload['title'] . "\n");
            $printer->setEmphasis(false);
            if ($payload['advance_order_label']) {
                $printer->text($payload['advance_order_label'] . "\n");
            }
            $printer->text(str_repeat('-', 32) . "\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            foreach ($payload['meta'] as [$label, $value]) {
                $printer->text("{$label}: {$value}\n");
            }

            foreach ($payload['batches'] as $batch) {
                $printer->text(str_repeat('-', 32) . "\n");
                if ($batch['label']) {
                    $printer->setEmphasis(true);
                    $printer->text(strtoupper($batch['label']) . "\n");
                    $printer->setEmphasis(false);
                }
                foreach ($batch['items'] as $item) {
                    $printer->setEmphasis(true);
                    $printer->text($item['line'] . "\n");
                    $printer->setEmphasis(false);
                    foreach ($item['sub_lines'] as $subLine) {
                        $printer->text('  ' . $subLine . "\n");
                    }
                }
            }

            if ($payload['notes']) {
                $printer->text(str_repeat('-', 32) . "\n");
                $printer->text($payload['notes_label'] . ": {$payload['notes']}\n");
            }

            $printer->text(str_repeat('-', 32) . "\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($payload['footer'] . "\n");
            $printer->feed(3);
            $printer->cut();
        });
    }

    /**
     * Opens a connection, runs $callback with the live Printer instance,
     * then always closes — mirrors escpos-php's own recommended try/finally
     * usage so callers never have to think about connection lifecycle.
     */
    public function withPrinter(callable $callback): void
    {
        [$connector, $host] = $this->connect();
        $printer = new Printer($connector);

        try {
            $callback($printer);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Failed to print to thermal printer at {$host}:{$this->port} — {$e->getMessage()}",
                previous: $e,
            );
        } finally {
            $printer->close();
        }
    }

    /**
     * @return array{0: NetworkPrintConnector, 1: string}  the live connector and the host it reached
     */
    private function connect(): array
    {
        $failures = [];

        foreach ($this->hosts as $host) {
            try {
                return [new NetworkPrintConnector($host, $this->port, self::CONNECT_TIMEOUT_SECONDS), $host];
            } catch (Throwable $e) {
                $failures[] = "{$host}:{$this->port} ({$e->getMessage()})";
            }
        }

        throw new RuntimeException('No thermal printer answered. Tried: '.implode(', ', $failures));
    }
}
