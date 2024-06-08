<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Data\Messages\Utils\ScriptFactory;

trait HandlesApiRequestData
{
    protected array $data = [];

    public function data() : array {
        $context = [
            'json_schema' => $this->responseModel()?->toJsonSchema(),
        ];
        $script = ScriptFactory::make(
            $this->messages(),
            $this->prompt(),
            $this->dataAcknowledgedPrompt,
            $this->examples()
        );

        return [
            'script_context' => $context,
            'script' => $script,
        ];
    }
}