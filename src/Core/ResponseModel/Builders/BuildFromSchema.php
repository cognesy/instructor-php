<?php

namespace Cognesy\Instructor\Core\ResponseModel\Builders;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

class BuildFromSchema extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel
    {
        return $this->makeSchemaResponseModel($requestedModel);
    }

    private function makeSchemaResponseModel(Schema $requestedModel) : ResponseModel {
        $schema = $requestedModel;
        $class = $schema->type->class;
        $instance = new $class;
        $schema = $requestedModel;
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