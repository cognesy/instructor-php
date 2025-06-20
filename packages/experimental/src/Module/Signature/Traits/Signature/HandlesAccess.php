<?php
namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

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
        $firstOutput = $this->outputNames()[0];
        return (count($this->outputNames()) == 1)
            && (
                $this->output->isArray()
                || $this->output->getPropertySchema($firstOutput)->isArray()
            );
    }

    public function hasObjectOutput(): bool {
        $firstOutput = $this->outputNames()[0];
        return (count($this->outputNames()) == 1)
            && (
                $this->output->isObject()
                || $this->output->getPropertySchema($firstOutput)->isObject()
            );
    }

    public function hasEnumOutput(): bool {
        $firstOutput = $this->outputNames()[0];
        return (count($this->outputNames()) == 1)
            && (
                $this->output->isEnum()
                || $this->output->getPropertySchema($firstOutput)->isEnum()
            );
    }

    public function hasScalarOutput(): bool {
        $firstOutput = $this->outputNames()[0];
        return (count($this->outputNames()) == 1)
            && (
                $this->output->isScalar()
                || $this->output->getPropertySchema($firstOutput)->isScalar()
            );
    }

    public function hasTextOutput(): bool {
        $firstOutput = $this->outputNames()[0];
        return $this->hasScalarOutput()
            && (
                $this->output->typeDetails()->isString()
                || $this->output->getPropertySchema($firstOutput)->typeDetails()->isString()
            );
    }
}