<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

trait HandlesSelfExtraction {
    public static function extract(
        string|array $messages,
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        array $examples = [],
        string $toolName = '',
        string $toolDescription = '',
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
        Instructor $instructor = null,
    ) : static {
        return ($instructor ?? new Instructor)->respond(
            messages: $messages,
            responseModel: self::class,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            examples: $examples,
            toolName: $toolName,
            toolDescription: $toolDescription,
            prompt: $prompt,
            retryPrompt: $retryPrompt,
            mode: $mode,
        );
    }
}
