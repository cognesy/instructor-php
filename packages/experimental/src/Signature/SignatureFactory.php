<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature;

use Cognesy\Dynamic\Structure;
use Cognesy\Experimental\Signature\Factories\SignatureFromCallable;
use Cognesy\Experimental\Signature\Factories\SignatureFromClassMetadata;
use Cognesy\Experimental\Signature\Factories\SignatureFromString;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
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
        $schemaFactory = SchemaFactory::default();
        $outputSchema = $schemaFactory->schema($output);

        return new Signature(
            input: $schemaFactory->schema($input),
            output: $outputSchema,
            description: $outputSchema->description(),
        );
    }

    static public function fromClassMetadata(
        string $class,
        string $description = '',
    ): Signature {
        return (new SignatureFromClassMetadata)->make($class, $description);
    }

    /**
     * @deprecated Transitional adapter. Prefer fromSchemas()/fromSchema() with schema-first APIs.
     */
    static public function fromStructures(
        Structure $inputs,
        Structure $outputs,
        string $description = '',
    ) : Signature {
        // Transitional adapter for callsites still passing Dynamic Structure.
        return self::fromSchemas(
            input: $inputs->schema(),
            output: $outputs->schema(),
            description: $description ?: $outputs->description(),
        );
    }

    /**
     * @deprecated Transitional adapter. Prefer fromSchema() with schema-first APIs.
     */
    static public function fromStructure(
        Structure $structure,
        string $description = '',
    ) : Signature {
        // Transitional adapter for callsites still passing Dynamic Structure.
        return self::fromSchema($structure->schema(), $description);
    }

    static public function fromSchemas(
        Schema $input,
        Schema $output,
        string $description = '',
    ) : Signature {
        return new Signature(
            input: $input,
            output: $output,
            description: $description !== '' ? $description : $output->description(),
        );
    }

    static public function fromSchema(
        Schema $schema,
        string $description = '',
    ) : Signature {
        if (!($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema)) {
            throw new \InvalidArgumentException('Invalid schema, expected object schema with inputs and outputs fields.');
        }

        if (!$schema->hasProperty('inputs') || !$schema->hasProperty('outputs')) {
            throw new \InvalidArgumentException('Invalid schema, missing "inputs" or "outputs" fields.');
        }

        $inputSchema = $schema->getPropertySchema('inputs');
        $outputSchema = $schema->getPropertySchema('outputs');
        if (!$inputSchema->hasProperties() || !$outputSchema->hasProperties()) {
            throw new \InvalidArgumentException('Invalid schema, "inputs" and "outputs" must be object schemas.');
        }

        return self::fromSchemas(
            input: $inputSchema,
            output: $outputSchema,
            description: $description !== '' ? $description : $outputSchema->description(),
        );
    }

    static public function fromString(
        string $signatureString,
        string $description = '',
    ): Signature {
        return (new SignatureFromString)->make($signatureString, $description);
    }
}
