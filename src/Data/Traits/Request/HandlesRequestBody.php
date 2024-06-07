<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Core\Messages\Utils\MessageBuilder;
use Cognesy\Instructor\Enums\Mode;
use Exception;

trait HandlesRequestBody
{
    protected function toApiRequestBody() : array {
        $body = MessageBuilder::requestBody(
            clientClass: match (true) {
                is_null($this->client()) => throw new Exception('Client not set'),
                default => get_class($this->client()),
            },
            mode: $this->mode(),
            messages: $this->messages(),
            responseModel: $this->responseModel(),
            dataAcknowledgedPrompt: $this->dataAcknowledgedPrompt,
            prompt: $this->prompt(),
            examples: $this->examples(),
        );

        $body['model'] = $this->model();
        if (Mode::Tools == $this->mode()) {
            $body['tools'] = $this->toolCallSchema();
            $body['tool_choice'] = $this->toolChoice();
        } elseif (Mode::Json == $this->mode()) {
            $body['response_format'] = $this->responseFormat();
        }
        $body = array_merge($body, $this->options());

        return array_merge(
            $this->options,
            $body,
        );
    }
}