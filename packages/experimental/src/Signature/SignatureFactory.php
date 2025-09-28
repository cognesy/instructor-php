<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature;

use Cognesy\Dynamic\Structure;
use Cognesy\Experimental\Signature\Factories\SignatureFromCallable;
use Cognesy\Experimental\Signature\Factories\SignatureFromClassMetadata;
use Cognesy\Experimental\Signature\Factories\SignatureFromRequest;
use Cognesy\Experimental\Signature\Factories\SignatureFromString;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Reflection\ClassInfo;

class SignatureFactory
{
    static public function fromCallable(callable $callable): Signature {
        return (new SignatureFromCallable)->make($callable);
    }

    static public function fromClassMethod(
        string|object $class,
        string $method
    ): Signature {
        return (new SignatureFromCallable)->make([$class, $method]);
    }

    static public function fromClasses(
        string|object $input,
        string|object $output
    ): Signature {
        $outputClass = is_string($output) ? $output : get_class($output);
        $signature = new Signature(
            input: (new SchemaFactory)->schema($input),
            output: (new SchemaFactory)->schema($output),
            description: ClassInfo::fromString($outputClass)->getClassDescription(),
        );
        return $signature;
    }

    static public function fromClassMetadata(
        string $class,
        string $description = '',
    ): Signature {
        return (new SignatureFromClassMetadata)->make($class, $description);
    }

    static public function fromRequest(StructuredOutputRequest $request): Signature {
        return (new SignatureFromRequest)->make($request);
    }

    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
        string $description = '',
    ) : Signature {
        return new Signature(
            input: $inputs->schema(),
            output: $outputs->schema(),
            description: $description ?: $outputs->description(),
        );
    }

    static public function fromStructure(
        Structure $structure,
        string $description = '',
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
            description: $description ?: $structure->outputs->description(),
        );
    }

    static public function fromString(
        string $signatureString,
        string $description = '',
    ): Signature {
        return (new SignatureFromString)->make($signatureString, $description);
    }
}
