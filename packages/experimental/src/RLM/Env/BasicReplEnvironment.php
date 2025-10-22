<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Env;

use Cognesy\Experimental\RLM\Contracts\ReplEnvironment;
use Cognesy\Experimental\RLM\Data\Handles\VarHandle;
use Cognesy\Experimental\RLM\Data\Repl\CodeResultHandle;
use Cognesy\Experimental\RLM\Data\Repl\ReplInventory;

final class BasicReplEnvironment implements ReplEnvironment
{
    /** @var array<string,mixed> */
    private array $vars = [];

    public function __construct(array $initial = []) {
        $this->vars = $initial;
    }

    public function inventory(): ReplInventory {
        $names = array_keys($this->vars);
        return new ReplInventory(variableNames: $names, artifactNamespaces: ['artifact://rlm/*']);
    }

    public function runCode(string $code): CodeResultHandle {
        // v1 placeholder: do not execute arbitrary code. Return a handle describing a noop.
        // Future: sandboxed PHP mini-DSL runner.
        return new CodeResultHandle('artifact://rlm/code/noop');
    }

    public function writeVar(string $name, mixed $value): VarHandle {
        $this->vars[$name] = $value;
        return new VarHandle($name);
    }

    public function readVar(string $name): VarHandle {
        return new VarHandle($name);
    }

    /** For tests/inspection only */
    public function variableValue(string $name): mixed {
        return $this->vars[$name] ?? null;
    }
}

