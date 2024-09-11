<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

interface CanInferSelf
{
    static public function infer(
        string|array $messages = '',
        string|array|object $input = '',
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        array $examples = [],
        string $prompt = '',
        Mode $mode = Mode::Tools,
        Instructor $instructor = null,
    ) : static;
}
