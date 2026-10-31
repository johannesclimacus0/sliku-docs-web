<?php

declare(strict_types=1);

namespace App\Console\UI;

class Miku
{
    private const DOTS = [[0, 3], [1, 4], [2, 5], [6, 7]];
    private array $originals = [];
    private array $widths = [];

    /** @return list<string> */
    public function names(): array
    {
        $files = glob(dirname(__DIR__, 3) . '/resources/console/art/*.txt') ?: [];
        $names = [];
        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $names[] = basename($file, '.txt');
            }
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    public function maxWidth(int $columns, int $rows): int
    {
        $key = $columns . 'x' . $rows;
        if (!isset($this->widths[$key])) {
            $width = 0;
            foreach ($this->names() as $name) {
                foreach (explode("\n", $this->render($name, $columns, $rows)) as $line) {
                    $width = max($width, mb_strlen($line));
                }
            }
            $this->widths[$key] = $width;
        }
        return $this->widths[$key];
    }

    public function render(string $artName, int $columns, int $rows): string
    {
        if ($columns < 14 || $rows < 7) {
            return '';
        }
        $lines = $this->load($artName);
        $width = max(array_map(mb_strlen(...), $lines));
        $height = count($lines);
        $scale = min(1, $columns / $width, $rows / $height);
        if ($scale >= 1) {
            return implode("\n", $lines);
        }
        $outWidth = max(1, (int) floor($width * $scale));
        $outHeight = max(1, (int) floor($height * $scale));
        $cells = array_map(static fn (string $line): array => array_map(
            static fn (string $char): int => max(0, mb_ord($char) - 0x2800),
            mb_str_split($line),
        ), $lines);
        $output = [];
        for ($y = 0; $y < $outHeight; $y++) {
            $line = '';
            for ($x = 0; $x < $outWidth; $x++) {
                $mask = 0;
                foreach (self::DOTS as $dy => $bits) {
                    foreach ($bits as $dx => $bit) {
                        $sourceX = (int) floor(($x * 2 + $dx + 0.5) * $width / $outWidth);
                        $sourceY = (int) floor(($y * 4 + $dy + 0.5) * $height / $outHeight);
                        $sourceMask = $cells[intdiv($sourceY, 4)][intdiv($sourceX, 2)] ?? 0;
                        if ($sourceMask & (1 << self::DOTS[$sourceY % 4][$sourceX % 2])) {
                            $mask |= 1 << $bit;
                        }
                    }
                }
                $line .= mb_chr(0x2800 + $mask);
            }
            $output[] = $line;
        }
        return implode("\n", $output);
    }

    public function colored(string $artName, int $columns, int $rows): string
    {
        $picture = $this->render($artName, $columns, $rows);
        if ($picture === '') {
            return $picture;
        }
        $original = $this->load($artName);
        $sourceWidth = max(array_map(mb_strlen(...), $original));
        $sourceHeight = count($original);
        $colors = MikuColors::colors($artName, $sourceWidth, $sourceHeight);
        if ($colors === null) {
            return $picture;
        }
        $lines = explode("\n", $picture);
        $width = max(array_map(mb_strlen(...), $lines));
        $height = count($lines);
        $cells = array_map(static fn (string $line): array => array_map(
            static fn (string $char): int => max(0, mb_ord($char) - 0x2800),
            mb_str_split($line),
        ), $original);
        foreach ($lines as $y => &$line) {
            $output = '';
            $previous = null;
            foreach (mb_str_split($line) as $x => $char) {
                $votes = [];
                foreach (self::DOTS as $dy => $bits) {
                    foreach ($bits as $dx => $bit) {
                        $sx = (int) floor(($x * 2 + $dx + 0.5) * $sourceWidth / $width);
                        $sy = (int) floor(($y * 4 + $dy + 0.5) * $sourceHeight / $height);
                        $mask = $cells[intdiv($sy, 4)][intdiv($sx, 2)] ?? 0;
                        if ($mask & (1 << self::DOTS[$sy % 4][$sx % 2])) {
                            $color = $colors[intdiv($sy, 4)][intdiv($sx, 2)] ?? null;
                            if ($color !== null) {
                                $votes[$color] = ($votes[$color] ?? 0) + 1;
                            }
                        }
                    }
                }
                arsort($votes);
                $color = array_key_first($votes);
                if ($color !== $previous) {
                    $output .= $color === null ? "\033[39m" : "\033[38;2;{$color}m";
                    $previous = $color;
                }
                $output .= $char;
            }
            $line = $output . "\033[39m";
        }
        unset($line);

        return implode("\n", $lines);
    }

    private function load(string $artName): array
    {
        if (!in_array($artName, $this->names(), true)) {
            throw new \InvalidArgumentException('Unknown miku: ' . $artName);
        }
        if (!isset($this->originals[$artName])) {
            $file = dirname(__DIR__, 3) . '/resources/console/art/' . $artName . '.txt';
            $text = file_get_contents($file);
            if ($text === false || preg_match('/\A[\x{2800}-\x{28ff} \r\n]+\z/u', $text) !== 1) {
                throw new \RuntimeException('Miku art must contain UTF-8 braille characters');
            }
            $lines = str_replace("\r", '', $text)
                    |> (fn ($x) => explode("\n", $x))
                    |> (fn ($x) => array_filter($x, static fn (string $line): bool => preg_match('/[^ ⠀]/u', $line) === 1))
                    |> array_values(...);
            if ($lines === []) {
                throw new \RuntimeException('Miku art is empty');
            }
            $indent = min(array_map(static function (string $line): int {
                preg_match('/^[ ⠀]*/u', $line, $match);
                return mb_strlen($match[0]);
            }, $lines));
            $this->originals[$artName] = array_map(static fn (string $line): string => preg_replace('/[ ⠀]+$/u', '', mb_substr($line, $indent)), $lines);
        }
        return $this->originals[$artName];
    }
}
