<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\ResponseModel;

class BuildFromSchemaProvider extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        return $this->makeSchemaProviderResponseModel($requestedModel);
    }

    private function makeSchemaProviderResponseModel(mixed $requestedModel) : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaBuilder->fromArray($jsonSchema);
        $functionCall = $this->functionCallBuilder->render(
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
            $functionCall,
        );
    }
}