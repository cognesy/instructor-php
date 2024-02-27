<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Instructor;

trait ExtractableMixin {
    static public function extract(
        string|array $messages,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 2,
        array $options = [],
        Instructor $instructor = null
    ) : static {
        $_instructor = $instructor ?? new Instructor();
        if (is_string($messages)) {
            $input = [['role' => 'user', 'content' => $messages]];
        } else {
            $input = $messages;
        }
        return $_instructor->extract(
            $input,
            static::class,
            $model,
            $maxRetries,
            $options
        );
    }
}
