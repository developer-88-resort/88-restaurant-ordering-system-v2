<?php

namespace App\Services\Printing;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Screenshots a slip's HTML with headless Chrome so the thermal printer can
 * print the picture rather than re-implementing the layout in ESC/POS. That
 * is the only way Direct Print and the browser Print button come out truly
 * identical — the same stylesheet draws both.
 *
 * Runs on the bridge machine, never on the production server: the HTML
 * arrives inside the print job, so nothing here needs a browser installed
 * on the VPS or credentials to fetch a page.
 */
class SlipImageRenderer
{
    /**
     * Printable dots across on an 80mm Epson TM-T82X — 72mm of printable
     * area at 8 dots/mm.
     */
    private const PRINTABLE_DOTS = 576;

    /** The printer's resolution, and the unit the slip's CSS is written in. */
    private const DOTS_PER_MM = 8;

    /**
     * The slip's page is 80mm wide but only 72mm of that can be printed —
     * the difference is the body's own side padding, which exists precisely
     * because a thermal head can't reach the paper's edges. So the page is
     * rendered at 80mm worth of dots and the printable 72mm is cropped out
     * of the middle. Mapping the full 80mm onto 576 instead would squeeze
     * the design into 90% of its intended size, which is what it did at
     * first and why the print came out small.
     */
    private const PAGE_MM = 80;

    /** 80mm expressed in CSS pixels (96dpi) — the slip's own body width. */
    private const PAGE_CSS_WIDTH = 302;

    /**
     * Chrome is given a canvas far wider than the slip and the result is
     * cropped back. Sizing the window to the slip exactly puts the body's
     * 80mm up against the viewport edge, and it spills instead of fitting.
     */
    private const CANVAS_CSS_WIDTH = 900;

    /** Tall enough for any realistic slip; the blank tail gets trimmed off. */
    private const PAGE_CSS_HEIGHT = 2000;

    public function __construct(
        private readonly string $chromeBinary,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    public static function fromConfig(): self
    {
        $binary = config('printing.chrome_binary') ?: self::detectChrome();

        if (! $binary) {
            throw new RuntimeException('No Chrome/Edge binary found. Set CHROME_BINARY in .env to its full path.');
        }

        return new self($binary, (int) config('printing.image_timeout', 30));
    }

    /**
     * @return string  path to a PNG the caller owns and should unlink
     */
    public function render(string $html): string
    {
        $workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slip-'.bin2hex(random_bytes(6));
        mkdir($workDir);

        $htmlPath = $workDir.DIRECTORY_SEPARATOR.'slip.html';
        $pngPath = $workDir.DIRECTORY_SEPARATOR.'slip.png';

        file_put_contents($htmlPath, $html);

        // Scaled so one CSS millimetre lands on one millimetre of paper at the
        // printer's own resolution. The crop below then never has to resample,
        // so the text stays as sharp as the browser drew it.
        $scale = (self::PAGE_MM * self::DOTS_PER_MM) / self::PAGE_CSS_WIDTH;

        $process = new Process([
            $this->chromeBinary,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--hide-scrollbars',
            '--default-background-color=FFFFFFFF',
            '--force-device-scale-factor='.round($scale, 4),
            '--window-size='.self::CANVAS_CSS_WIDTH.','.self::PAGE_CSS_HEIGHT,
            '--screenshot='.$pngPath,
            'file:///'.str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        if (! file_exists($pngPath)) {
            $this->cleanUp($workDir);

            throw new RuntimeException('Chrome produced no screenshot: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $this->cropToSlip($pngPath);

        return $pngPath;
    }

    public function cleanUp(string $pathInsideWorkDir): void
    {
        $dir = is_dir($pathInsideWorkDir) ? $pathInsideWorkDir : dirname($pathInsideWorkDir);

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /**
     * Cuts the slip out of Chrome's oversized canvas: exactly the printer's
     * dot count across, centred on the text, and ending at the last inked
     * row so no blank paper feeds before the cut. Deliberately a crop and
     * never a resize — resampling this much fine monospace text is what
     * turns a crisp slip into a grey smudge on a thermal head.
     */
    private function cropToSlip(string $pngPath): void
    {
        $image = imagecreatefrompng($pngPath);
        if ($image === false) {
            return;
        }

        $bounds = $this->inkBounds($image);
        if ($bounds === null) {
            return;
        }

        [$minX, $maxX, $maxY] = $bounds;

        // Centre the paper's width on the text rather than on the canvas, so
        // the slip sits where the printer's own margins expect it.
        $centre = intdiv($minX + $maxX, 2);
        $left = max(0, min($centre - intdiv(self::PRINTABLE_DOTS, 2), imagesx($image) - self::PRINTABLE_DOTS));

        $cropped = imagecrop($image, [
            'x' => $left,
            'y' => 0,
            'width' => self::PRINTABLE_DOTS,
            'height' => $maxY + 1,
        ]);

        if ($cropped !== false) {
            imagepng($cropped, $pngPath);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null  leftmost, rightmost and lowest inked pixel
     */
    private function inkBounds(\GdImage $image): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $minX = $width;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Anything meaningfully darker than paper counts as content.
                if (((imagecolorat($image, $x, $y) >> 16) & 0xFF) < 240) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $maxY = $y;
                }
            }
        }

        return $maxX < 0 ? null : [$minX, $maxX, $maxY];
    }

    private static function detectChrome(): ?string
    {
        $candidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
