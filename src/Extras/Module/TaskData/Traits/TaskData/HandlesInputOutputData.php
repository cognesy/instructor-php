<?php

namespace Cognesy\Instructor\Extras\Module\TaskData\Traits\TaskData;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;

trait HandlesInputOutputData
{
    public function withArgs(mixed ...$args): static {
        $this->input->setValues(
            InputOutputMapper::fromInputs($args, $this->inputNames())
        );
        return $this;
    }

    public function input(): DataAccess {
        return $this->input;
    }

    public function output(): DataAccess {
        return $this->output;
    }

    public function toArray(): array {
        return array_merge(
            $this->input->getValues(),
            $this->output->getValues(),
        );
    }

    // CONVENIENCE METHODS ////////////////////////////////////////////////////////////////

    public function inputNames(): array {
        return $this->signature->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->signature->toOutputSchema()->getPropertyNames();
    }
}
