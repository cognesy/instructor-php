<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\CallData;

use Cognesy\Instructor\Extras\Module\DataAccess\Contracts\DataAccess;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;

trait HandlesInputOutputData
{
    use ProvidesSchema;

    protected DataAccess $input;
    protected DataAccess $output;

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
}
