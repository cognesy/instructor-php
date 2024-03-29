<?php

namespace Cognesy\Instructor\Core\ResponseModel\Builders;

use Cognesy\Instructor\Data\ResponseModel;

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
        $jsonSchema = $schema->toArray($this->toolCallBuilder->onObjectRef(...));
        $toolCall = $this->toolCallBuilder->render(
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
            $toolCall,
        );
    }
}