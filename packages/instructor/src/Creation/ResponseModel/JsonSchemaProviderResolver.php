<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;

/** Resolves a CanProvideJsonSchema class-string or instance: schema comes from toJsonSchema(). */
final readonly class JsonSchemaProviderResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(object|string $requestedModel, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchemaProvider']));

        [$class, $instance] = $this->support->resolveClassAndInstance($requestedModel);
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->support->schemaRenderer()->schemaFromJsonSchema($jsonSchema);

        return $this->support->assemble(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $this->support->schemaName($requestedModel),
            schemaDescription: $this->support->schemaDescription($requestedModel),
            outputFormat: $outputFormat,
        );
    }
}
