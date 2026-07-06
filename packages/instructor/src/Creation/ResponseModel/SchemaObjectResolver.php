<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;
use InvalidArgumentException;

/**
 * Resolves a Schema object (ObjectSchema): class comes from the schema's type
 * info; class-less or Structure-typed schemas materialize a dynamic Structure.
 */
final readonly class SchemaObjectResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(Schema $schema, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchema']));

        $schemaClass = TypeInfo::className($schema->type);
        $isStructureSchema = $schema instanceof ObjectSchema && match (true) {
            $schemaClass === null => true,
            $schemaClass === \stdClass::class => true,
            $schemaClass === Structure::class => true,
            is_subclass_of($schemaClass, Structure::class) => true,
            default => false,
        };
        [$class, $instance] = match (true) {
            $isStructureSchema => [Structure::class, Structure::fromSchema($schema)],
            $schemaClass === null => throw new InvalidArgumentException('Schema must have a class to create ResponseModel'),
            default => [$schemaClass, $this->support->makeInstance($schemaClass)],
        };

        $rendering = $this->support->renderSchema($schema);

        return $this->support->assemble(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $rendering->jsonSchema(),
            schemaName: $this->support->schemaName($schema),
            schemaDescription: $this->support->schemaDescription($schema),
            outputFormat: $outputFormat,
            rendering: $rendering,
        );
    }
}
