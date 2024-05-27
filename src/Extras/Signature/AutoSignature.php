<?php

namespace Cognesy\Instructor\Extras\Signature;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataStructure;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;

class AutoSignature implements Signature
{
    use Traits\HandlesAutoConfig;
    use Traits\ConvertsToString;

    public const ARROW = '->';

    protected CanHandleDataStructure $inputs;
    protected CanHandleDataStructure $outputs;
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

    public function getInputs(): CanHandleDataStructure {
        return $this->inputs;
    }

    public function asInputArgs(): array {
        return $this->inputs->fieldValues();
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleDataField[] */
    public function getInputFields(): array {
        return $this->inputs->fields();
    }

    public function getOutputs(): CanHandleDataStructure {
        return $this->outputs;
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleDataField[] */
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