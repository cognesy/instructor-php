<?php

namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Structure\Structure;

trait CreatesFromStructure
{
    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
    ) : Signature {
        return new StructureSignature(
            inputs: $inputs,
            outputs: $outputs,
        );
    }

    static public function fromStructure(Structure $structure) : Signature {
        if (!$structure->has('inputs') || !$structure->has('outputs')) {
            throw new \InvalidArgumentException('Invalid structure, missing "inputs" or "outputs" fields');
        }
        $signature = new StructureSignature(
            inputs: Structure::define('inputs', $structure->inputs->fields()),
            outputs: Structure::define('outputs', $structure->outputs->fields()),
        );
        return $signature;
    }
}