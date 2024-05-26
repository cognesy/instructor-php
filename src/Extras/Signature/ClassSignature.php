<?php

namespace Cognesy\Instructor\Extras\Signature;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;

class ClassSignature implements Signature
{
    use Traits\HandlesAutoConfig;
    use Traits\ConvertsToString;

    public const ARROW = '->';

    protected Structure $inputs;
    protected Structure $outputs;
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

    public function getInputs(): Structure {
        return $this->inputs;
    }

    public function asInputArgs(): array {
        return $this->inputs->asArgs();
    }

    /** @return Field[] */
    public function getInputFields(): array {
        return $this->inputs->fields();
    }

    public function getOutputs(): Structure {
        return $this->outputs;
    }

    /** @return Field[] */
    public function getOutputFields(): array {
        return $this->outputs->fields();
    }

    public function asOutputValues(): array {
        return $this->inputs->asValues();
    }

    public function getDescription(): string {
        return $this->description;
    }
}