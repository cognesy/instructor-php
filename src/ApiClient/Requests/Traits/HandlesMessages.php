<?php
namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesMessages
{
    public function messages(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system', 'messages', 'data_ack', 'command', 'examples'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}