<?php

namespace Cognesy\Instructor\Extras\Signature;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;

class Signature
{
    use Traits\CreatesFromCallable;
    use Traits\CreatesFromClasses;
    use Traits\CreatesFromClassMetadata;
    use Traits\CreatesFromString;
    use Traits\CreatesFromStructure;
    use Traits\ConvertsToString;

    private const ARROW = '->';

    private Structure $inputs;
    private Structure $outputs;
    private string $description;

    protected function __construct(
        string|Signature $signature = null,
        Structure $inputs = null,
        Structure $outputs = null,
        string $description = null,
    ) {
        if (isset($inputs)) {
            $this->inputs = $inputs;
        }
        if (isset($outputs)) {
            $this->outputs = $outputs;
        }
        if ($signature instanceof Signature) {
            $this->inputs = $signature->inputs;
            $this->outputs = $signature->outputs;
        }
        if (is_string($signature)) {
            $signature = self::fromString($signature);
            $this->inputs = $signature->inputs;
        }
        if (isset($description)) {
            $this->description = $description;
        }
    }

    public function isSetUp(): bool {
        return isset($this->inputs) && isset($this->outputs);
    }

    public function getInputs(): Structure {
        return $this->inputs;
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
}
