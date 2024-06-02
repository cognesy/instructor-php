<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallDataClass;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\DataAccess\ObjectDataAccess;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;

trait HandlesInputOutputData
{
    static public function fromArgs(...$args): static {
        $instance = new static();
        $instance->withArgs(...$args);
        return $instance;
    }

    public function withArgs(...$args): static {
        $this->input()->setValues(
            InputOutputMapper::fromInputs($args, $this->inputNames())
        );
        return $this;
    }

    public function input(): DataAccess {
        if (!isset($this->input)) {
            $this->input = new ObjectDataAccess($this, $this->inputNames());
        }
        return $this->input;
    }

    public function output(): DataAccess {
        if (!isset($this->output)) {
            $this->output = new ObjectDataAccess($this, $this->outputNames());
        }
        return $this->output;
    }

    public function toArray(): array {
        return array_merge(
            $this->input()->getValues(),
            $this->output()->getValues(),
        );
    }

    // CONVENIENCE METHODS ////////////////////////////////////////////////////////////////

    public function inputNames(): array {
        return $this->signature()->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->signature()->toOutputSchema()->getPropertyNames();
    }
}
