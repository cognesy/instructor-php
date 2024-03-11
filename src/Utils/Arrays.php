<?php

namespace Cognesy\Instructor\Utils;

class Arrays
{
    static public function flatten(array $arrays, string $separator): string
    {
        return self::doFlatten($arrays, $separator);
    }

    static private function doFlatten(array $arrays, string $separator): string
    {
        $flat = '';
        foreach ($arrays as $item) {
            if (is_array($item)) {
                $flattenedItem = self::doFlatten($item, $separator);
                if ($flattenedItem !== '') {
                    $flat .= $flattenedItem . $separator;
                }
            } else {
                $trimmedItem = trim((string) $item);
                if ($trimmedItem !== '') {
                    $flat .= $trimmedItem . $separator;
                }
            }
        }
        return rtrim($flat, $separator);
    }
}