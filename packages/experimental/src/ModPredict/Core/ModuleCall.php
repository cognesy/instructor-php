<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Core;

use AllowDynamicProperties;
use Closure;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;
use Throwable;

#[AllowDynamicProperties]
class ModuleCall
{
    protected array $inputs = [];
    protected Closure $moduleCall;
    protected ?array $outputs = null;
    protected array $errors = [];

    public function __construct(
        array $inputs = [],
        Closure $moduleCall = null,
    ) {
        $this->inputs = $inputs;
        $this->moduleCall = $moduleCall;
    }

    // DYNAMIC ACCESS /////////////////////////////////////////////////

    public function __get(string $name) : mixed {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value) : void {
        throw new InvalidArgumentException('Cannot modify ModuleCall values');
    }

    public function __isset(string $name) : bool {
        return $this->hasInput($name) || $this->hasOutput($name);
    }

    // ACCESSORS ///////////////////////////////////////////////////////

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function asExample() : Example {
        return new Example(
            input: $this->inputs(),
            output: $this->outputs(),
        );
    }

    // INPUTS & OUTPUTS ///////////////////////////////////////////////

    public function inputs() : array {
        return $this->inputs;
    }

    public function hasInput(string $name) : bool {
        return isset($this->inputs[$name]);
    }

    public function inputFields() : array {
        return array_keys($this->inputs());
    }

    public function has(string $name) : bool {
        return $this->hasInput($name) || $this->hasOutput($name);
    }

    public function hasOutput(string $name) : bool {
        return isset($this->outputs()[$name]);
    }

    public function outputFields() : array {
        return array_keys($this->outputs());
    }

    public function get(?string $name = null) : mixed {
        return match(true) {
            empty($name) => $this->result(),
            !$this->hasOutput($name) => throw new InvalidArgumentException("Output field `$name` not found"),
            default => $this->outputs()[$name],
        };
    }

    public function result(): mixed {
        $fields = $this->outputFields();
        $outputs = $this->outputs();
        $firstIndex = $fields[0] ?? null;
        return match(true) {
            is_null($firstIndex) => null,
            (count($fields) == 1) => $outputs[$firstIndex],
            default => $this->outputs(),
        };
    }

    public function outputs() : array {
        if (is_null($this->outputs)) {
            $this->outputs = $this->execute();
        }
        return $this->outputs;
    }

    public function try(): Result {
        try {
            $result = $this->result();
            return Result::success($result);
        } catch (Throwable $e) {
            $this->errors[] = $e;
            return Result::failure($e);
        }
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    private function execute() : mixed {
        return ($this->moduleCall)($this->inputs);
    }
}
