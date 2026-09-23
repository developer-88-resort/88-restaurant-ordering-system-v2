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
     * The most raster data one image may carry. GS ( L — the graphics
     * command — gives its length in two bytes (65,535), which also counts
     * m + fn (2) and tone/scale/colour/width/height (8).
     *
     * escpos-php v2.2 doesn't enforce that: an operator-precedence slip in
     * its range check lets a bigger image through with the length silently
     * wrapped. A slip taller than ~910 dots then told the printer it was a
     * fraction of its real size, and the printer read the rest of the
     * picture as commands — nothing printed, and the garbage left the
     * printer ignoring every job after it (the browser Print button
     * included) until it was reset. So a slip is sent as bands that each fit.
     */
    public const GRAPHICS_MAX_DATA_BYTES = 65535 - 2 - 8;

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
    public function printImage(string $pngPath, int $copies = 1, int $pauseSeconds = 0): void
    {
        $bands = SlipEscposImage::bandsFromPng($pngPath, self::GRAPHICS_MAX_DATA_BYTES);

        $this->printCopies($copies, $pauseSeconds, function (Printer $printer) use ($bands): void {
            foreach ($bands as $band) {
                $printer->graphics($band);
            }
        });
    }

    /**
     * Puts one slip's content on paper $copies times, from a single press.
     *
     * With a pause, each copy is its own slip — printed, cut, and then the
     * printer rests before starting the next, long enough for the last one to
     * be taken off. With no pause they come out as one continuous length of
     * paper with a marked tear line between them.
     *
     * @param  callable(Printer): void  $writeSlip
     */
    protected function printCopies(int $copies, int $pauseSeconds, callable $writeSlip): void
    {
        $copies = max(1, $copies);
        $pauseSeconds = max(0, $pauseSeconds);

        $this->withPrinter(function (Printer $printer) use ($copies, $pauseSeconds, $writeSlip): void {
            for ($copy = 1; $copy <= $copies; $copy++) {
                if ($copy > 1) {
                    $pauseSeconds > 0
                        ? $this->pause($pauseSeconds)
                        : $this->copySeparator($printer, $copy, $copies);
                }

                $writeSlip($printer);

                if ($pauseSeconds > 0) {
                    $printer->feed(3);
                    $printer->cut();
                }
            }

            if ($pauseSeconds === 0) {
                $printer->feed(3);
                $printer->cut();
            }
        });
    }

    /** Its own method so tests can watch the wait without sitting through it. */
    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Several copies come out as ONE continuous slip with a marked tear line
     * between them, cut once at the end.
     *
     * The alternative — releasing the next copy only once the previous one
     * has been taken — needs the printer to sense that the paper was removed,
     * and the TM-T82X only reports whether it HAS paper (DLE EOT n=4:
     * near-end / out). So copies are never left half-queued waiting for a
     * hand that the printer can't feel.
     */
    protected function copySeparator(Printer $printer, int $copy, int $copies): void
    {
        $printer->feed(1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(str_repeat('- ', 16)."\n");
        $printer->setEmphasis(true);
        $printer->text("COPY {$copy} OF {$copies}\n");
        $printer->setEmphasis(false);
        $printer->text(str_repeat('- ', 16)."\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed(1);
    }

    /**
     * Renders a payload built by KitchenSlipPayloadBuilder. Deliberately
     * dumb — every piece of business logic (what counts as cancelled, who
     * "Waiter"/"Ordered By" means, translations) was already resolved
     * server-side when the payload was built; this only lays plain text
     * out on the paper.
     *
     */
    public function printKitchenSlip(array $payload, int $copies = 1, int $pauseSeconds = 0): void
    {
        $this->printCopies($copies, $pauseSeconds, fn (Printer $printer) => $this->writeKitchenSlip($printer, $payload));
    }

    /**
     * The slip's text layout, without the closing feed and cut — so several
     * copies can share one length of paper.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function writeKitchenSlip(Printer $printer, array $payload): void
    {
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
                $printer->text(($item['amount'] ?? null) !== null ? $this->columns($item['line'], $item['amount']) : $item['line'] . "\n");
                $printer->setEmphasis(false);
                foreach ($item['sub_lines'] as $subLine) {
                    $printer->text('  ' . $subLine . "\n");
                }
            }
        }

        // Slips queued before prices were added carry no totals.
        if (! empty($payload['totals'])) {
            $printer->text(str_repeat('-', 32) . "\n");
            foreach ($payload['totals'] as [$label, $value]) {
                $printer->text($this->columns($label, $value));
            }
            $printer->setEmphasis(true);
            $printer->text($this->columns($payload['total'][0], $payload['total'][1]));
            $printer->setEmphasis(false);
        }

        if ($payload['notes']) {
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->text($payload['notes_label'] . ": {$payload['notes']}\n");
        }

        $printer->text(str_repeat('-', 32) . "\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text($payload['footer'] . "\n");
    }

    /**
     * One "label ....... value" line in the printer's default font, which
     * fits 42 characters on 80 mm paper with the margins it keeps. A label
     * too long to share the line gets the value on a line of its own.
     */
    protected function columns(string $label, string $value, int $width = 42): string
    {
        $gap = $width - mb_strlen($label) - mb_strlen($value);

        return $gap >= 1
            ? $label . str_repeat(' ', $gap) . $value . "\n"
            : $label . "\n" . str_repeat(' ', max(0, $width - mb_strlen($value))) . $value . "\n";
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
