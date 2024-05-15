<?php

namespace Cognesy\Instructor\Clients\Anthropic\Traits;

use Override;

trait HandlesStreamData
{
    #[Override]
    protected function isDone(string $data): bool {
        return $data === 'event: message_stop';
    }

    #[Override]
    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        // ignore event lines
        return '';
    }
}