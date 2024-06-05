<?php

namespace Cognesy\Instructor\Clients\Cohere\Traits;

use Override;

trait HandlesStreamData
{
    #[Override]
    protected function isDone(string $data): bool {
        return $data === '[DONE]';
    }

    #[Override]
    protected function getData(string $data): string {
        return trim($data);
    }
}