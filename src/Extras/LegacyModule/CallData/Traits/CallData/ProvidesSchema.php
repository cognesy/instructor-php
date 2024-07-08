<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallData;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait ProvidesSchema
{
    use ProvidesSignature;

    public function toSchema(): Schema {
        return $this->toOutputSchema();
    }

    public function toInputSchema(): Schema {
        return $this->signature()->toInputSchema();
    }

    public function toOutputSchema(): Schema {
        return $this->signature()->toOutputSchema();
    }

    public function inputNames(): array {
        return $this->toInputSchema()->getPropertyNames();
    }

    public function outputNames(): array {
        return $this->toOutputSchema()->getPropertyNames();
    }
}