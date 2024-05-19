<?php

namespace Cognesy\Instructor\Utils;

class Template
{
    public static function render(string $template, array $data): string {
        $keys = array_map(fn($key) => '{'.$key.'}', array_keys($data));
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $values[$key] = Json::encode($value);
            }
        }
        $values = array_values($data);
        return str_replace($keys, $values, $template);
    }
}
