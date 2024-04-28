<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Instructor;

interface CanHandleExtraction
{
    static public function extract(
        string|array $messages,
        string $model,
        int $maxRetries = 2,
        array $options = [],
        Instructor $instructor = null
    ) : static;
}
