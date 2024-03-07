<?php

namespace Cognesy\Instructor\Utils;

class Arrays {
    static public function flatten(array $arrays, string $separator): string {
        $flat = '';
        foreach ($arrays as $item) {
            if (is_array($item)) {
                $flat .= self::flatten($item, $separator);
            }
            else {
                $flat .= trim($item) . $separator;
            }
        }
        return trim($flat);
    }
}
