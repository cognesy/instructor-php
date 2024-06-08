<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Data\Messages\Utils\ScriptFactory;

trait HandlesApiRequestData
{
    protected array $data = [];

    public function data() : array {
        return array_filter(array_merge(
            $this->data,
            [
                'mode' => $this->mode(),
                'tools' => $this->toolCallSchema(),
                'tool_choice' => $this->toolChoice(),
                'response_format' => $this->responseFormat(),
                'script_context' => [
                    'json_schema' => $this->responseModel()?->toJsonSchema(),
                ],
                'script' => ScriptFactory::make(
                    $this->messages(),
                    $this->dataAckPrompt,
                    $this->prompt(),
                    $this->examples(),
                    $this->retryPrompt(),
                ),
            ]
        ));
    }
}
