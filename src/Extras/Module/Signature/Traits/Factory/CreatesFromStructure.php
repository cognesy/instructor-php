<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\StructureSignature;

trait CreatesFromStructure
{
    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
    ) : HasSignature {
        return new StructureSignature(
            inputs: $inputs,
            outputs: $outputs,
        );
    }

    static public function fromStructure(Structure $structure) : HasSignature {
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