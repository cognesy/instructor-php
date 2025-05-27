<?php
namespace Cognesy\Instructor\Data\Traits\ResponseModel;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Schema\Factories\ToolCallBuilder;

trait HandlesToolCalls
{
    private ToolCallBuilder $toolCallBuilder;

    private string $toolName = '';
    private string $toolDescription = '';

    public function toolName() : string {
        return $this->toolName;
    }

    public function withToolName(string $toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function toolDescription() : string {
        return $this->toolDescription;
    }

    public function withToolDescription(string $toolDescription) : static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function toolCallSchema() : array {
        return match(true) {
            $this->instance() instanceof CanHandleToolSelection => $this->instance()->toToolCallsJson(),
            default => $this->toolCallBuilder->renderToolCall(
                $this->toJsonSchema(),
                $this->toolName,
                $this->toolDescription
            ),
        };
    }
}