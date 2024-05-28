<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\Traits\ConvertsToString;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

class StructureSignature implements Signature
{
    use ConvertsToString;

    protected Structure $inputs;
    protected Structure $outputs;
    protected string $description = '';
    protected string $prompt = 'Your task is to find output arguments in input data based on specification: {signature} {description}';

    public function __construct(
        string|Signature $signature = null,
        Structure $inputs = null,
        Structure $outputs = null,
        string $description = null,
    ) {
        if (!is_null($inputs)) {
            $this->inputs = $inputs;
        }
        if (!is_null($outputs)) {
            $this->outputs = $outputs;
        }
        if (is_string($signature)) {
            $signature = SignatureFactory::fromString($signature);
            $this->inputs = $signature->inputs;
        }
        if ($signature instanceof Signature) {
            $this->inputs = $signature->inputs;
            $this->outputs = $signature->outputs;
        }
        if (!is_null($description)) {
            $this->description = $description;
        }
    }

    public function getInputs(): Structure {
        return $this->inputs;
    }

    public function getInputValues(): array {
        return $this->inputs->fieldValues();
    }

    /** @return \Cognesy\Instructor\Extras\Structure\Field[] */
    public function getInputFields(): array {
        return $this->inputs->fields();
    }

    public function getOutputs(): Structure {
        return $this->outputs;
    }

    /** @return \Cognesy\Instructor\Extras\Structure\Field[] */
    public function getOutputFields(): array {
        return $this->outputs->fields();
    }

    public function getOutputValues(): array {
        return $this->inputs->fieldValues();
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function withInputClass(string|object $input): static {
        $this->inputs = $this->makeStructureFromClass($input);
        return $this;
    }

    public function withOutputClass(string|object $output): static {
        $this->outputs = $this->makeStructureFromClass($output);
        return $this;
    }

    public function getInputSchema(): Schema {
        return $this->inputs->schema();
    }

    public function getOutputSchema(): Schema {
        return $this->outputs->schema();
    }

    protected function makeStructureFromClass(string|object $class): Structure {
        $class = is_string($class) ? $class : get_class($class);
        return StructureFactory::fromClass($class);
    }
}