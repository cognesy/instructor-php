<?php

namespace Cognesy\Instructor\Utils;

class Template
{
    public static function render(string $template, array $data): string {
        $keys = array_map(fn($key) => '{'.$key.'}', array_keys($data));
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[$key] = match (true) {
                is_array($value) || is_object($value) => Json::encode($value),
                default => $value,
            };
        }
        $values = array_values($normalized);
        return trim(str_replace($keys, $values, $template));
    }
}
