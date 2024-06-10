<?php
namespace Cognesy\Instructor\Clients\Anthropic\Traits;

trait HandlesStreamData
{
    protected function isDone(string $data): bool {
        return $data === 'event: message_stop';
    }

    protected function getData(string $data): string {
        if (str_starts_with($data, 'data:')) {
            return trim(substr($data, 5));
        }
        return '';
    }
}