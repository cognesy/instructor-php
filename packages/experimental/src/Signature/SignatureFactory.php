<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature;

use Cognesy\Dynamic\Structure;
use Cognesy\Experimental\Signature\Factories\SignatureFromCallable;
use Cognesy\Experimental\Signature\Factories\SignatureFromClassMetadata;
use Cognesy\Experimental\Signature\Factories\SignatureFromString;
use Cognesy\Schema\SchemaFactory;

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
        $schemaFactory = new SchemaFactory();
        $outputSchema = $schemaFactory->schema($output);
        $signature = new Signature(
            input: $schemaFactory->schema($input),
            output: $outputSchema,
            description: $outputSchema->description(),
        );
        return $signature;
    }

    static public function fromClassMetadata(
        string $class,
        string $description = '',
    ): Signature {
        return (new SignatureFromClassMetadata)->make($class, $description);
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

        $inputSchema = $structure->field('inputs')->schema();
        $outputSchema = $structure->field('outputs')->schema();
        if (!$inputSchema->hasProperties() || !$outputSchema->hasProperties()) {
            throw new \InvalidArgumentException('Invalid structure, "inputs" and "outputs" must be object schemas.');
        }

        return new Signature(
            input: $inputSchema,
            output: $outputSchema,
            description: $description ?: $outputSchema->description(),
        );
    }

    static public function fromString(
        string $signatureString,
        string $description = '',
    ): Signature {
        return (new SignatureFromString)->make($signatureString, $description);
    }
}
