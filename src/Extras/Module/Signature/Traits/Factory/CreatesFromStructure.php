<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;

trait CreatesFromStructure
{
    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
    ) : Signature {
        return new Signature(
            input: $inputs->schema(),
            output: $outputs->schema(),
            description: $outputs->description(),
        );
    }

    static public function fromStructure(
        Structure $structure,
    ) : Signature {
        // check if structure has inputs and outputs fields
        if (!$structure->has('inputs') || !$structure->has('outputs')) {
            throw new \InvalidArgumentException('Invalid structure, missing "inputs" or "outputs" fields');
        }
        // check if inputs and outputs are structures
        if (!$structure->field('inputs')->typeDetails()->class instanceof Structure) {
            throw new \InvalidArgumentException('Invalid structure, "inputs" field must be Structure');
        }
        if (!$structure->field('outputs')->typeDetails()->class instanceof Structure) {
            throw new \InvalidArgumentException('Invalid structure, "outputs" field must be Structure');
        }
        return new Signature(
            input: $structure->inputs->schema(),
            output: $structure->outputs->schema(),
            description: '',
        );
    }
}
