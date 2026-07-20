<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;
use Cognesy\Schema\SchemaFactory;

/**
 * Resolves an array treated as a JSON Schema document. `x-php-class` selects
 * the default target class; a class-less schema defaults to an array.
 */
final readonly class JsonSchemaResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(array $jsonSchema, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchema']));

        $rawClass = $jsonSchema['x-php-class'] ?? '';
        $class = match (true) {
            is_string($rawClass) && $rawClass !== '' => ltrim($rawClass, '\\'),
            default => '',
        };

        $schemaName = $this->support->schemaName($jsonSchema);
        if ($outputFormat === null && $class !== '' && !class_exists($class)) {
            throw new \InvalidArgumentException(
                "Output class does not exist for schema {$schemaName}: {$class}",
            );
        }
        $schemaDescription = $this->support->schemaDescription($jsonSchema);
        $schema = SchemaFactory::withMetadata(
            $this->support->schemaRenderer()->schemaFromJsonSchema($jsonSchema),
            name: $schemaName,
            description: $schemaDescription,
        );

        return $this->support->assemble(
            instance: null,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $outputFormat,
        );
    }
}
