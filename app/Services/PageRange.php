<?php

namespace App\Services;

use InvalidArgumentException;

class PageRange
{
    /** "1-5, 27, 30-35" -> [1,2,3,4,5,27,30,...] sorted, unique, validated against $max. */
    public static function parse(string $spec, int $max): array
    {
        $pages = [];
        foreach (preg_split('/[,\s;]+/', trim($spec), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
                [$a, $b] = [(int) $m[1], (int) $m[2]];
                if ($a > $b) {
                    throw new InvalidArgumentException("Invalid range \"$part\".");
                }
                for ($i = $a; $i <= $b; $i++) {
                    $pages[$i] = true;
                    if (count($pages) > $max) {
                        break;
                    }
                }
            } elseif (preg_match('/^\d+$/', $part)) {
                $pages[(int) $part] = true;
            } else {
                throw new InvalidArgumentException("Invalid page \"$part\".");
            }
        }
        $pages = array_keys($pages);
        sort($pages);
        if (!$pages) {
            throw new InvalidArgumentException('No pages selected.');
        }
        foreach ($pages as $p) {
            if ($p < 1 || $p > $max) {
                throw new InvalidArgumentException("Page $p does not exist (document has $max pages).");
            }
        }
        return $pages;
    }
}
