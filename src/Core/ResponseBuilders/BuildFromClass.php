<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\Data\ResponseModel;

class BuildFromClass extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        return $this->makeStringResponseModel($requestedModel);
    }

    private function makeStringResponseModel(string $requestedModel) : ResponseModel {
        $class = $requestedModel;
        $instance = new $class;
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