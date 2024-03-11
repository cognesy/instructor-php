<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\ResponseModel;

class BuildFromInstance extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        return $this->makeInstanceResponseModel($requestedModel);
    }

    private function makeInstanceResponseModel(object $requestedModel) : ResponseModel {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
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