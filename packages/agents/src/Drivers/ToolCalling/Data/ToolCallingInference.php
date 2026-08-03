<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling\Data;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

final readonly class ToolCallingInference
{
    public function __construct(
        private AgentState $state,
        private InferenceRequest $request,
        private InferenceResponse $response,
    ) {}

    public function state(): AgentState {
        return $this->state;
    }

    public function request(): InferenceRequest {
        return $this->request;
    }

    public function response(): InferenceResponse {
        return $this->response;
    }
}
