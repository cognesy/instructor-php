<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;

/** Resolves a CanProvideSchema class-string or instance: schema comes from toSchema(). */
final readonly class SchemaProviderResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(object|string $requestedModel, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchemaProvider']));

        [$class, $instance] = $this->support->resolveClassAndInstance($requestedModel);
        $schema = $instance->toSchema();
        $rendering = $this->support->renderSchema($schema);

        return $this->support->assemble(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $rendering->jsonSchema(),
            schemaName: $this->support->schemaName($schema),
            schemaDescription: $this->support->schemaDescription($requestedModel),
            outputFormat: $outputFormat,
            rendering: $rendering,
        );
    }
}
