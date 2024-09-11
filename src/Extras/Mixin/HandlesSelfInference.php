<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

trait HandlesSelfInference {
    public static function infer(
        string|array $messages = '',
        string|array|object $input = '',
        string $prompt = '',
        array $examples = [],
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        Mode $mode = Mode::Tools,
        Instructor $instructor = null,
    ) : static {
        $instructor = $instructor ?? new Instructor;

        return $instructor->respond(
            messages: $messages,
            input: $input,
            responseModel: self::class,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            mode: $mode,
        );
    }
}
