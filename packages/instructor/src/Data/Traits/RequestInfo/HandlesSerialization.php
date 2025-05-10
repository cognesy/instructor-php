<?php

namespace Cognesy\Instructor\Data\Traits\RequestInfo;

trait HandlesSerialization
{
    public function toArray() : array {
        return [
            'messages' => $this->messages,
            'input' => $this->input,
            'responseModel' => $this->responseModel,
            'model' => $this->model,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'maxRetries' => $this->maxRetries,
            'options' => $this->options,
            'examples' => array_map(fn($example) => $example->jsonSerialize(), $this->examples),
            'retryPrompt' => $this->retryPrompt,
            'toolName' => $this->toolName,
            'toolDescription' => $this->toolDescription,
            'mode' => $this->mode->value,
            'cachedContext' => $this->cachedContext,
        ];
    }
}