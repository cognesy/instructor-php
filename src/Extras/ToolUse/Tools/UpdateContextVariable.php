<?php

namespace Cognesy\Instructor\Extras\ToolUse\Tools;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanAccessContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class UpdateContextVariable extends BaseTool implements CanAccessContext
{
    protected string $name = 'update_context_variable';
    protected string $description = 'Update a variable in the context';

    private ToolUseContext $context;

    public function __invoke(string $variableName, string $jsonValue): mixed {
        $this->context->setVariable($variableName, json_decode($jsonValue, true));
        return null;
    }

    public function withContext(ToolUseContext $context): self {
        $this->context = $context;
        return $this;
    }
}