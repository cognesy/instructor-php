<?php

namespace Cognesy\Instructor\Core\ResponseModel\Builders;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Data\ResponseModel;

class BuildFromJsonSchemaProvider extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        return $this->makeSchemaProviderResponseModel($class, $instance);
    }

    private function makeSchemaProviderResponseModel(
        string $class,
        CanProvideJsonSchema $instance
    ) : ResponseModel {
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaBuilder->fromArray($jsonSchema);
        $toolCallSchema = $this->toolCallBuilder->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $toolCallSchema
        );
    }
}