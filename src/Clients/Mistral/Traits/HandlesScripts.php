<?php
namespace Cognesy\Instructor\Clients\Mistral\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesScripts
{
    protected function fromScript(Script $script, array $context) : array {
        $script->withContext($context);
        return [
            'messages' => $script
                ->select(['system', 'prompt', 'data_ack', 'examples', 'messages'])
                ->toNativeArray(ClientType::Mistral),
        ];
    }
}