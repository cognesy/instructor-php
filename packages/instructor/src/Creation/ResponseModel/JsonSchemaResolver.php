<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;
use Cognesy\Schema\SchemaFactory;

/**
 * Resolves an array treated as a JSON Schema document. `x-php-class` selects
 * the target class; anything Structure-like materializes a dynamic Structure.
 */
final readonly class JsonSchemaResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(array $jsonSchema, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchema']));

        $rawClass = $jsonSchema['x-php-class'] ?? Structure::class;
        $class = match (true) {
            is_string($rawClass) && $rawClass !== '' => ltrim($rawClass, '\\'),
            default => Structure::class,
        };

        $schemaName = $this->support->schemaName($jsonSchema);
        $schemaDescription = $this->support->schemaDescription($jsonSchema);
        $schema = SchemaFactory::withMetadata(
            $this->support->schemaRenderer()->schemaFromJsonSchema($jsonSchema),
            name: $schemaName,
            description: $schemaDescription,
        );

        $isStructureSchema = match (true) {
            $class === \stdClass::class => true,
            $class === Structure::class => true,
            is_subclass_of($class, Structure::class) => true,
            default => false,
        };

        return $this->support->assemble(
            class: $isStructureSchema ? Structure::class : $class,
            instance: $isStructureSchema ? Structure::fromSchema($schema) : $this->support->makeInstance($class),
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $outputFormat,
        );
    }
}
