<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesAccess
{
    public function getDescription(): string {
        return $this->description;
    }

    public function getInstructions() : string {
        return $this->compiled;
    }

    public function inputNames(): array {
        return $this->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->toOutputSchema()->getPropertyNames();
    }

    public function hasScalarOutput(): bool {
        return (count($this->outputNames()) == 1)
            && ($this->output->isScalar() || $this->output->getPropertySchema($this->outputNames()[0])->isScalar());
    }

    public function hasTextOutput(): bool {
        return $this->hasScalarOutput() && ($this->output->typeDetails()->type() === TypeDetails::PHP_STRING
                || $this->output->getPropertySchema($this->outputNames()[0])->typeDetails()->type() === TypeDetails::PHP_STRING
            );
    }
}