<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\Data\ResponseModel;
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