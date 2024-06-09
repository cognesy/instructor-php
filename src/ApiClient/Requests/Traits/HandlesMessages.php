<?php
namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesMessages
{
    public function messages(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system', 'messages', 'input', 'data_ack', 'prompt', 'examples', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}