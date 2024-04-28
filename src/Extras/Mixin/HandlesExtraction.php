<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Instructor;

trait HandlesExtraction {
    static public function extract(
        string|array $messages,
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        Instructor $instructor = null
    ) : static {
        return ($instructor ?? new Instructor)->respond(
            $messages,
            static::class,
            $model,
            $maxRetries,
            $options
        );
    }
}
