<?php

namespace Cognesy\Instructor\Extras\Module\CallData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\CallData\CallData;
use Cognesy\Instructor\Extras\Structure\Structure;

trait CreatesFromStructure
{
    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
    ) : CallData {
        return new CallData(
            input: $inputs,
            output: $outputs,
            signature: SignatureFactory::fromStructures($inputs, $outputs),
        );
    }

    static public function fromStructure(Structure $structure) : HasInputOutputData {
        if (!$structure->has('inputs') || !$structure->has('outputs')) {
            throw new \InvalidArgumentException('Invalid structure, missing "inputs" or "outputs" fields');
        }
        if (!$structure->field('inputs')->typeDetails()->class instanceof Structure) {
            throw new \InvalidArgumentException('Invalid structure, "inputs" field must be Structure');
        }
        if (!$structure->field('outputs')->typeDetails()->class instanceof Structure) {
            throw new \InvalidArgumentException('Invalid structure, "outputs" field must be Structure');
        }
        $callData = new CallData(
            input: Structure::define('inputs', $structure->inputs->fields()),
            output: Structure::define('outputs', $structure->outputs->fields()),
            signature: SignatureFactory::fromStructure($structure),
        );
        return $callData;
    }
}