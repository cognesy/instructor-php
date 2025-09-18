<?php
namespace Cognesy\Experimental\Module\Core\Traits\ModuleCall;

use InvalidArgumentException;
use Cognesy\Utils\Result\Result;
use Throwable;

trait HandlesOutputs
{
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
        return match(true) {
            (count($this->outputFields()) == 1) => $this->outputs()[0],
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
}