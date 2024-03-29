<?php
namespace Cognesy\Instructor\Core\ResponseModel\Builders;

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
        $schema = $instance->toSchema();
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