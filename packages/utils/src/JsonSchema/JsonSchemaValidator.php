<?php

namespace Cognesy\Utils\JsonSchema;

class JsonSchemaValidator
{
    public function validate(JsonSchema $schema) : void {
        $this->validateType($schema->type());
        $this->validateProperties($schema->properties());
        $this->validateRequired($schema);
        $this->validateProperty($schema->itemSchema());
        $this->validateEnum($schema->enumValues());
    }

    // INTERNAL //////////////////////////////////////////////////////////

    private function validateType(string $type) : void {
        if (empty($type)) {
            return;
        }
        if (!in_array($type, JsonSchema::JSON_TYPES)) {
            throw new \Exception("Invalid type: {$type}");
        }
    }

    private function validateRequired(JsonSchema $schema) : void {
        if ($schema->requiredProperties() === null) {
            return;
        }
        foreach ($schema->requiredProperties() as $propertyName) {
            if (is_null($schema->property($propertyName))) {
                throw new \Exception("Required property does not exist: {$propertyName}");
            }
        }
    }

    private function validateEnum(?array $enum) : void {
        if ($enum === null) {
            return;
        }
        foreach ($enum as $value) {
            if (!is_string($value)) {
                throw new \Exception('Invalid enum value: ' . print_r($value, true));
            }
        }
    }

    private function validateProperties(?array $properties) : void {
        if ($properties === null) {
            return;
        }
        foreach ($properties as $propertyName => $schema) {
            switch($schema->type()) {
                case JsonSchema::JSON_OBJECT:
                    $this->validateObjectProperty($schema);
                    break;
                case JsonSchema::JSON_ARRAY:
                    $this->validateArrayProperty($schema);
                    break;
                default:
                    $this->validateProperty($schema);
            }
        }
    }

    private function validateProperty(?JsonSchema $schema) : void {
        if ($schema === null || empty($schema)) {
            return;
        }
        if (empty($schema->type())) {
            throw new \Exception('Invalid property: missing "type"');
        }
        if (!in_array($schema->type(), JsonSchema::JSON_TYPES)) {
            throw new \Exception("Invalid property type: {$schema->type()}");
        }
    }

    private function validateArrayProperty(?JsonSchema $schema) : void {
        if ($schema === null) {
            return;
        }
        if (empty($schema->type())) {
            throw new \Exception('Invalid property: missing "type"');
        }
        if (!empty($schema->itemSchema())) {
            $this->validateProperty($schema->itemSchema());
        }
    }

    private function validateObjectProperty(?JsonSchema $schema) : void {
        if ($schema === null) {
            return;
        }
        if (empty($schema->type())) {
            throw new \Exception('Invalid property: missing "type"');
        }

        // properties can be empty - when additionalProperties = true
        // but we cannot validate it here - as additionalProperties may be
        // set via fluent method = after constructor execution has been
        // completed
        //if (empty($schema->properties()) && !$schema->hasAdditionalProperties()) {
        //    throw new \Exception('Invalid property: empty "properties"');
        //}

        foreach ($schema->properties() as $propertyName => $propertySchema) {
            $this->validateProperty($propertySchema);
        }
    }
}