<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data\Traits\ResponseModel;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

trait HandlesToolCalls
{
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
        $schemaFactory = new SchemaFactory(useObjectReferences: $this->useObjectReferences);
        $toolCallBuilder = new ToolCallBuilder($schemaFactory);

        return match(true) {
            $this->instance() instanceof CanHandleToolSelection => $this->instance()->toToolCallsJson(),
            default => $toolCallBuilder->renderToolCall(
                $this->toJsonSchema(),
                $this->toolName,
                $this->toolDescription
            ),
        };
    }
}