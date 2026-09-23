<?php

namespace Tests\Unit;

use App\Services\Printing\ThermalPrinterService;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use PHPUnit\Framework\TestCase;

/**
 * When the kitchen wants several copies of a slip they come out as ONE
 * continuous length of paper with a marked tear line between them and a
 * single cut at the end — never as separate jobs, and never half-queued
 * waiting for someone to take the previous slip (the TM-T82X cannot sense
 * that). These tests read the bytes that would reach the printer.
 */
class ThermalPrinterCopiesTest extends TestCase
{
    private string $png;

    protected function tearDown(): void
    {
        if (isset($this->png) && is_file($this->png)) {
            unlink($this->png);
        }

        parent::tearDown();
    }

    public function test_one_press_prints_one_slip_with_one_cut(): void
    {
        $this->png = $this->makePng();
        $printer = new SpyPrinterService;

        $printer->printImage($this->png);

        $this->assertSame(1, $this->imagesPrinted($printer->bytes));
        $this->assertSame(1, $this->cuts($printer->bytes));
        $this->assertStringNotContainsString('COPY', $printer->bytes, 'A single copy needs no separator.');
    }

    public function test_several_copies_come_out_as_one_slip_with_a_tear_line_between_them(): void
    {
        $this->png = $this->makePng();
        $printer = new SpyPrinterService;

        $printer->printImage($this->png, 3);

        $this->assertSame(3, $this->imagesPrinted($printer->bytes), 'Every copy is printed.');
        $this->assertSame(1, $this->cuts($printer->bytes), 'Cut once, at the end — one continuous slip.');
        $this->assertStringContainsString('COPY 2 OF 3', $printer->bytes);
        $this->assertStringContainsString('COPY 3 OF 3', $printer->bytes);
        $this->assertStringContainsString('- - -', $printer->bytes, 'The tear line marks where one copy ends.');
    }

    public function test_three_copies_with_a_pause_come_out_as_three_separate_slips(): void
    {
        $this->png = $this->makePng();
        $printer = new SpyPrinterService;

        $printer->printImage($this->png, 3, 3);

        $this->assertSame(3, $this->imagesPrinted($printer->bytes), 'Three copies from the one press.');
        $this->assertSame(3, $this->cuts($printer->bytes), 'Each copy is cut off on its own.');
        $this->assertSame([3, 3], $printer->pauses, 'It waits between copies, not after the last one.');
        $this->assertStringNotContainsString('COPY', $printer->bytes, 'Separate slips need no tear line.');
    }

    public function test_a_single_copy_never_waits(): void
    {
        $this->png = $this->makePng();
        $printer = new SpyPrinterService;

        $printer->printImage($this->png, 1, 3);

        $this->assertSame(1, $this->imagesPrinted($printer->bytes));
        $this->assertSame(1, $this->cuts($printer->bytes));
        $this->assertSame([], $printer->pauses);
    }

    public function test_the_text_fallback_repeats_the_same_way(): void
    {
        $printer = new SpyPrinterService;

        $printer->printKitchenSlip($this->payload(), 2);

        $this->assertSame(2, substr_count($printer->bytes, 'Cottage 3 - Slip #2'), 'Both copies carry the slip.');
        $this->assertSame(1, $this->cuts($printer->bytes));
        $this->assertStringContainsString('COPY 2 OF 2', $printer->bytes);
    }

    public function test_a_nonsense_copy_count_still_prints_one_slip(): void
    {
        $printer = new SpyPrinterService;

        $printer->printKitchenSlip($this->payload(), 0);

        $this->assertSame(1, substr_count($printer->bytes, 'Cottage 3 - Slip #2'));
        $this->assertSame(1, $this->cuts($printer->bytes));
    }

    /** GS ( L with function 2: "print the stored graphics". */
    private function imagesPrinted(string $bytes): int
    {
        return substr_count($bytes, "\x1d(L\x02\x000\x32");
    }

    private function cuts(string $bytes): int
    {
        return substr_count($bytes, "\x1dV");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'KITCHEN SLIP',
            'advance_order_label' => null,
            // ASCII only: this printer drops characters it has no code page for.
            'meta' => [['Location', 'Cottage 3 - Slip #2']],
            'batches' => [['label' => null, 'items' => [['line' => '2x Adobo', 'sub_lines' => ['No sauce']]]]],
            'notes' => null,
            'notes_label' => 'Notes',
            'footer' => 'Thank you',
        ];
    }

    private function makePng(): string
    {
        $image = imagecreatetruecolor(576, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 10, 10, 560, 40, imagecolorallocate($image, 0, 0, 0));

        $path = tempnam(sys_get_temp_dir(), 'slip').'.png';
        imagepng($image, $path);

        return $path;
    }
}

/**
 * The real service opens a socket to the printer; this keeps everything else
 * exactly as it is and writes the bytes to memory instead.
 */
class SpyPrinterService extends ThermalPrinterService
{
    public string $bytes = '';

    /** @var array<int, int> seconds waited between copies */
    public array $pauses = [];

    public function __construct()
    {
        parent::__construct(hosts: ['printer.test'], port: 9100);
    }

    protected function pause(int $seconds): void
    {
        $this->pauses[] = $seconds;
    }

    public function withPrinter(callable $callback): void
    {
        $connector = new DummyPrintConnector;
        $printer = new Printer($connector);

        $callback($printer);

        $this->bytes = $connector->getData();
        $printer->close();
    }
}
