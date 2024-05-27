<?php

namespace Cognesy\Instructor\Extras\Signature;

use Cognesy\Instructor\Contracts\DataModel\CanHandleField;
use Cognesy\Instructor\Contracts\DataModel\CanHandleStructure;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;

class AutoSignature implements Signature
{
    use Traits\HandlesAutoConfig;
    use Traits\ConvertsToString;

    public const ARROW = '->';

    protected CanHandleStructure $inputs;
    protected CanHandleStructure $outputs;
    protected string $description = '';
    protected string $prompt = 'Your task is to find output arguments in input data based on specification: {signature} {description}';

    public function __construct(
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->autoConfigure();
    }

    public function getInputs(): CanHandleStructure {
        return $this->inputs;
    }

    public function asInputArgs(): array {
        return $this->inputs->fieldValues();
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleField[] */
    public function getInputFields(): array {
        return $this->inputs->fields();
    }

    public function getOutputs(): CanHandleStructure {
        return $this->outputs;
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleField[] */
    public function getOutputFields(): array {
        return $this->outputs->fields();
    }

    public function asOutputValues(): array {
        return $this->inputs->fieldValues();
    }

    public function getDescription(): string {
        return $this->description;
    }
}