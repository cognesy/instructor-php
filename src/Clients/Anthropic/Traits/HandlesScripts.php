<?php
namespace Cognesy\Instructor\Clients\Anthropic\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesScripts
{
    protected function fromScript(Script $script, array $context) : array {
        $script->withContext($context);
        return [
            'system' => $script->select('system')->toString(),
            'messages' => $script
                ->select(['messages', 'data_ack', 'command', 'examples'])
                ->toNativeArray(ClientType::Anthropic),
        ];
    }
}