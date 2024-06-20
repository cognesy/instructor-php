<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Contracts\CanMaterializeMessages;
use Cognesy\Instructor\Data\Messages\Script;

class BodyMaterializer implements CanMaterializeMessages
{
    public function toMessages(Script $script) : array {
        return $script->select([
            'system',
            'pre-input', 'messages', 'input', 'post-input',
            'pre-prompt', 'prompt', 'post-prompt',
            'pre-examples', 'examples', 'post-examples',
            'pre-retries', 'retries', 'post-retries'
        ])
        ->toArray();
    }

    public function toSystem(Script $script) : array {
        return [];
    }
}
