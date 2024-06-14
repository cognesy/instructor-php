<?php

namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;

interface CanExtract
{
    public function extract(
        string|array $messages = '',
        string|array|object $input = '',
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        array $examples = [],
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
    ) : mixed;
}
