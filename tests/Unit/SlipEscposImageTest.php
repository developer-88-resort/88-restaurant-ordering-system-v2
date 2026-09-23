<?php

namespace Tests\Unit;

use App\Services\Printing\SlipEscposImage;
use App\Services\Printing\ThermalPrinterService;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use PHPUnit\Framework\TestCase;

/**
 * Found 2026-09-17: Direct Print sent tall slips as one GS ( L image whose
 * two-byte length had silently wrapped. The printer read most of the
 * picture as commands, printed nothing, and then ignored every later job —
 * the browser Print button too — until it was reset. These tests read the
 * exact bytes that would go to the printer.
 */
class SlipEscposImageTest extends TestCase
{
    private string $png;

    protected function tearDown(): void
    {
        if (isset($this->png) && is_file($this->png)) {
            unlink($this->png);
        }

        parent::tearDown();
    }

    public function test_a_tall_slip_goes_out_as_well_formed_graphics_commands_covering_every_row(): void
    {
        // 576 dots wide (80mm) and far taller than one command can carry.
        $this->png = $this->makePng(576, 2500);

        $bytes = $this->printBands($this->png);
        $commands = $this->graphicsCommands($bytes);

        $stores = array_values(array_filter($commands, fn ($command) => $command['fn'] === 'p'));
        $prints = array_values(array_filter($commands, fn ($command) => $command['fn'] === '2'));

        $this->assertGreaterThan(1, count($stores), 'A slip this tall must be split into bands.');
        $this->assertCount(count($stores), $prints, 'Each stored band is printed.');

        $totalRows = 0;
        foreach ($stores as $store) {
            $this->assertLessThanOrEqual(65535, $store['declared']);

            // tone, x scale, y scale, colours, then width and height (2 bytes each)
            $width = ord($store['data'][4]) + ord($store['data'][5]) * 256;
            $rows = ord($store['data'][6]) + ord($store['data'][7]) * 256;

            $this->assertSame(576, $width);
            $this->assertSame(8 + intdiv($width + 7, 8) * $rows, strlen($store['data']), 'Declared size matches the raster actually sent.');
            $totalRows += $rows;
        }

        $this->assertSame(2500, $totalRows, 'No row of the slip is lost between bands.');
    }

    public function test_a_short_slip_is_still_a_single_image(): void
    {
        $this->png = $this->makePng(576, 400);

        $stores = array_filter($this->graphicsCommands($this->printBands($this->png)), fn ($command) => $command['fn'] === 'p');

        $this->assertCount(1, $stores);
    }

    private function printBands(string $png): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        foreach (SlipEscposImage::bandsFromPng($png, ThermalPrinterService::GRAPHICS_MAX_DATA_BYTES) as $band) {
            $printer->graphics($band);
        }

        $bytes = $connector->getData();
        $printer->close();

        return $bytes;
    }

    /**
     * Walks the stream command by command. If any declared length were
     * wrong, the next GS ( L would not start where the previous one ends
     * and the walk fails — exactly how the printer lost its place.
     *
     * @return array<int, array{fn: string, declared: int, data: string}>
     */
    private function graphicsCommands(string $bytes): array
    {
        $prefix = "\x1d(L";
        $commands = [];
        $offset = strpos($bytes, $prefix);
        $this->assertNotFalse($offset, 'No graphics command was sent.');

        while ($offset < strlen($bytes)) {
            $this->assertSame($prefix, substr($bytes, $offset, 3), "Expected the next command at byte {$offset}; the stream is out of step.");

            $declared = ord($bytes[$offset + 3]) + ord($bytes[$offset + 4]) * 256;
            $body = substr($bytes, $offset + 5, $declared);
            $this->assertSame($declared, strlen($body), 'The command claims more bytes than were sent.');

            $commands[] = ['fn' => $body[1], 'declared' => $declared, 'data' => substr($body, 2)];
            $offset += 5 + $declared;
        }

        return $commands;
    }

    private function makePng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $black = imagecolorallocate($image, 0, 0, 0);

        // Text-like stripes all the way down, so every band has ink.
        for ($y = 0; $y < $height; $y += 40) {
            imagefilledrectangle($image, 10, $y, $width - 10, $y + 12, $black);
        }

        $path = tempnam(sys_get_temp_dir(), 'slip').'.png';
        imagepng($image, $path);

        return $path;
    }
}
