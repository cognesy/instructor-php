<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\ResponseModel;
use Exception;

class BuildFromArray extends AbstractBuilder
{
    public function build(mixed $requestedModel) : ResponseModel {
        return $this->makeArrayResponseModel($requestedModel);
    }

    private function makeArrayResponseModel(array $requestedModel) : ResponseModel {
        $class = $requestedModel['$comment'] ?? null;
        if (empty($class)) {
            throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
        }
        $instance = new $class;
        $schema = $this->schemaBuilder->fromArray($requestedModel);
        $jsonSchema = $requestedModel;
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