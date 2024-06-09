<?php
namespace Cognesy\Instructor\Clients\OpenAI\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesScripts
{
    protected function fromScript(Script $script, array $context) : array {
        $script->withContext($context);
        return [
            'messages' => $script
                ->select(['system', 'messages', 'input', 'data_ack', 'prompt', 'examples'])
                ->toNativeArray(ClientType::OpenAI),
        ];
    }
}
