<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesApiRequestData
{
    protected array $data = [];

    public function data() : array {
        return array_filter(array_merge(
            $this->data,
            [
                'mode' => $this->mode(),
                'client_type' => ClientType::fromClientClass($this->client()),
                'tools' => $this->toolCallSchema() ?? [],
                'tool_choice' => $this->toolChoice() ?? [],
                'response_format' => $this->responseFormat() ?? [],
            ]
        ));
    }
}
