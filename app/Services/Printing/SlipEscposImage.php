<?php

namespace App\Services\Printing;

use Mike42\Escpos\EscposImage;
use RuntimeException;

/**
 * Loads a PNG for the printer.
 *
 * escpos-php is pinned at v2.2 (v5 needs ext-intl, which isn't installed),
 * and that release predates PHP 8: its GD loader still guards on
 * is_resource(), which a PHP 8 GdImage object fails, so every image throws
 * "Failed to load image" before a single pixel is read. This does the same
 * conversion the library would — one character per pixel, '1' for ink —
 * against the object API PHP 8 actually hands back.
 */
class SlipEscposImage extends EscposImage
{
    /** Below this average channel value a pixel is ink rather than paper. */
    private const INK_THRESHOLD = 128;

    public function __construct(string $pngPath)
    {
        parent::__construct(null, false);

        $image = @imagecreatefrompng($pngPath);

        if ($image === false) {
            throw new RuntimeException("Could not read slip image at {$pngPath}.");
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $pixels = str_repeat('0', $width * $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $brightness = ($colour['red'] + $colour['green'] + $colour['blue']) / 3;

                // A transparent pixel is paper, however dark its colour reads.
                $isInk = $brightness < self::INK_THRESHOLD && $colour['alpha'] < 64;

                $pixels[$y * $width + $x] = $isInk ? '1' : '0';
            }
        }

        $this->setImgWidth($width);
        $this->setImgHeight($height);
        $this->setImgData($pixels);
    }
}
