<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Script;

use Cognesy\Instructor\Data\Messages\ScriptContext;

trait HandlesContext
{
    private ?ScriptContext $context = null;

    public function context() : ScriptContext {
        return $this->context;
    }

    public function withContext(array|ScriptContext $context) : static {
        $this->context = match(true) {
            $context instanceof ScriptContext => $context,
            default => new ScriptContext($context),
        };
        return $this;
    }

    public function setContextVar(string $name, mixed $value) : static {
        $this->context->set($name, $value);
        return $this;
    }

    public function unsetContextVar(string $name) : static {
        $this->context->unset($name);
        return $this;
    }
}
