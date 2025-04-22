<?php
namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;

trait HandlesAccess
{
    public function getDescription(): string {
        return $this->description;
    }

    public function inputNames(): array {
        return $this->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->toOutputSchema()->getPropertyNames();
    }

    public function hasSingleOutput(): bool {
        return (count($this->outputNames()) == 1);
    }

    public function hasArrayOutput(): bool {
        return (count($this->outputNames()) == 1)
            && ($this->output->isArray() || $this->output->getPropertySchema($this->outputNames()[0])->isArray());
    }

    public function hasObjectOutput(): bool {
        return (count($this->outputNames()) == 1)
            && ($this->output->isObject() || $this->output->getPropertySchema($this->outputNames()[0])->isObject());
    }

    public function hasEnumOutput(): bool {
        return (count($this->outputNames()) == 1)
            && ($this->output->isEnum() || $this->output->getPropertySchema($this->outputNames()[0])->isEnum());
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