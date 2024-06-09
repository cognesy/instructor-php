<?php
namespace Cognesy\Instructor\Clients\Cohere\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesScripts
{
    protected function fromScript(Script $script, array $context) : array {
        $script->withContext($context);
        return array_filter([
            'preamble' => $script->select('system')->toString(),
            'chat_history' => $script->select('messages')->toNativeArray(ClientType::Cohere),
            'message' => $script->select(['prompt', 'examples'])->toString(),
        ]);
    }
}

