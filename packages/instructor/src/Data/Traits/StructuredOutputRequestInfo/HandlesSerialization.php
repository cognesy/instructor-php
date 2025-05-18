<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequestInfo;

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
            'options' => $this->options,
            'examples' => array_map(fn($example) => $example->jsonSerialize(), $this->examples),
            'cachedContext' => $this->cachedContext,
            'config' => $this->config->toArray(),
        ];
    }
}