<?php

namespace App\Support;

class CaptchaImage
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    // Five-column bitmap glyphs keep image generation independent of GD or fonts.
    private const GLYPHS = [
        '2' => [14, 17, 1, 2, 4, 8, 31], '3' => [30, 1, 1, 14, 1, 1, 30],
        '4' => [2, 6, 10, 18, 31, 2, 2], '5' => [31, 16, 16, 30, 1, 1, 30],
        '6' => [14, 16, 16, 30, 17, 17, 14], '7' => [31, 1, 2, 4, 8, 8, 8],
        '8' => [14, 17, 17, 14, 17, 17, 14], '9' => [14, 17, 17, 15, 1, 1, 14],
        'A' => [14, 17, 17, 31, 17, 17, 17], 'B' => [30, 17, 17, 30, 17, 17, 30],
        'C' => [14, 17, 16, 16, 16, 17, 14], 'D' => [30, 17, 17, 17, 17, 17, 30],
        'E' => [31, 16, 16, 30, 16, 16, 31], 'F' => [31, 16, 16, 30, 16, 16, 16],
        'G' => [14, 17, 16, 23, 17, 17, 15], 'H' => [17, 17, 17, 31, 17, 17, 17],
        'J' => [7, 2, 2, 2, 18, 18, 12], 'K' => [17, 18, 20, 24, 20, 18, 17],
        'M' => [17, 27, 21, 21, 17, 17, 17], 'N' => [17, 25, 25, 21, 19, 19, 17],
        'P' => [30, 17, 17, 30, 16, 16, 16], 'Q' => [14, 17, 17, 17, 21, 18, 13],
        'R' => [30, 17, 17, 30, 20, 18, 17], 'S' => [15, 16, 16, 14, 1, 1, 30],
        'T' => [31, 4, 4, 4, 4, 4, 4], 'U' => [17, 17, 17, 17, 17, 17, 14],
        'V' => [17, 17, 17, 17, 17, 10, 4], 'W' => [17, 17, 17, 21, 21, 21, 10],
        'X' => [17, 17, 10, 4, 10, 17, 17], 'Y' => [17, 17, 10, 4, 4, 4, 4],
        'Z' => [31, 1, 2, 4, 8, 16, 31],
    ];

    public function render(string $code): string
    {
        $width = 240;
        $height = 96;
        $pixels = str_repeat("\xf3\xf6\xfa", $width * $height);
        $draw = static function (int $x, int $y, string $color) use (&$pixels, $width, $height) {
            if ($x >= 0 && $x < $width && $y >= 0 && $y < $height) {
                $offset = ($y * $width + $x) * 3;
                $pixels[$offset] = $color[0];
                $pixels[$offset + 1] = $color[1];
                $pixels[$offset + 2] = $color[2];
            }
        };

        for ($line = 0; $line < 12; $line++) {
            $base = random_int(8, 85);
            $phase = random_int(0, 20);
            for ($x = 0; $x < $width; $x++) {
                $draw($x, $base + (int) (8 * sin(($x + $phase) / 22)), "\xc1\xcb\xd8");
            }
        }
        for ($dot = 0; $dot < 650; $dot++) {
            $draw(random_int(0, $width - 1), random_int(0, $height - 1), "\xb0\xbf\xd0");
        }

        foreach (str_split($code) as $index => $character) {
            $left = 22 + $index * 42 + random_int(-4, 4);
            $top = random_int(23, 39);
            $scaleX = random_int(3, 4);
            $scaleY = random_int(4, 5);
            $angle = deg2rad(random_int(-25, 25));
            $phase = random_int(0, 20);
            $color = pack('C3', random_int(25, 70), random_int(35, 75), random_int(55, 105));
            // Inverse sampling keeps rotated and warped strokes continuous.
            for ($y = -10; $y < 46; $y++) {
                for ($x = -10; $x < 31; $x++) {
                    $dx = $x - 10 - 3 * sin(($y + $phase) / 7);
                    $dy = $y - 17;
                    $column = (int) floor(($dx * cos($angle) + $dy * sin($angle) + 10) / $scaleX);
                    $row = (int) floor((-$dx * sin($angle) + $dy * cos($angle) + 17) / $scaleY);
                    if ($row >= 0 && $row < 7 && $column >= 0 && $column < 5
                        && (self::GLYPHS[$character][$row] & (1 << (4 - $column)))) {
                        $draw($left + $x, $top + $y, $color);
                    }
                }
            }
        }

        // Thin foreground curves cross the glyphs without covering whole strokes.
        for ($line = 0; $line < 3; $line++) {
            $base = random_int(34, 61);
            $phase = random_int(0, 50);
            for ($x = 0; $x < $width; $x++) {
                $draw($x, $base + (int) (10 * sin(($x + $phase) / 28)), "\x83\x94\xab");
            }
        }

        $scanlines = '';
        for ($y = 0; $y < $height; $y++) {
            $scanlines .= "\0".substr($pixels, $y * $width * 3, $width * 3);
        }
        $chunk = static fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($scanlines))
            .$chunk('IEND', '');
    }
}
