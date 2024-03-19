<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Data\ResponseModel;

class BuildFromSchemaProvider extends AbstractBuilder
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
        CanProvideSchema $instance
    ) : ResponseModel {
        $schema = $instance->toSchema($this->schemaFactory, $this->typeDetailsFactory);
        $jsonSchema = $schema->toArray($this->functionCallBuilder->onObjectRef(...));
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