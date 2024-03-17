<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\Data\ResponseModel;

class BuildFromInstance extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        return $this->makeInstanceResponseModel($class, $instance);
    }

    private function makeInstanceResponseModel(
        string $class,
        object $instance
    ) : ResponseModel {
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