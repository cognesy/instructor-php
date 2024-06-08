<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Enums\Mode;

trait HandlesApiRequestBody
{
    protected function toApiRequestBody() : array {
        if (Mode::Tools == $this->mode()) {
            $body['tools'] = $this->toolCallSchema();
            $body['tool_choice'] = $this->toolChoice();
        } else {
            $body['response_format'] = $this->responseFormat();
        }

        return array_merge(
            ['model' => $this->modelName()],
            $this->options(),
            $body,
        );
    }
}