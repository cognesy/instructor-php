<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits;

trait HandlesTransformation
{
    public function toJsonSchema() : array {
        return $this->toArray();
    }

    public function toString() : string {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function toArray() : array {
        if ($this->rawSchema !== null) {
            return $this->rawSchema;
        }

        $array = match(true) {
            $this->hasRef() => $this->refToArray(),
            $this->type->isObject() => $this->objectToArray(),
            $this->type->isArray() => $this->arrayToArray(),
            $this->type->isString() => $this->stringToArray(),
            $this->type->isBoolean() => $this->boolToArray(),
            $this->type->isNumber() => $this->numberToArray(),
            $this->type->isInteger() => $this->integerToArray(),
            $this->type->isAny() => $this->anyToArray(),
            default => throw new \Exception('Invalid type: ' . $this->type),
        };

        return $this->appendDefinitions($array);
    }

    public function toFunctionCall(
        string $functionName,
        string $functionDescription = '',
        bool $strict = false,
    ) : array {
        if (!$this->type->isObject()) {
            throw new \Exception('Cannot convert to function call: ' . $this->type);
        }
        return [
            'type' => 'function',
            'function' => [
                'name' => $functionName,
                'description' => $functionDescription,
                'parameters' => $this->toArray(),
            ],
            'strict' => $strict,
        ];
    }

    public function toResponseFormat(
        string $schemaName = '',
        string $schemaDescription = '',
        bool $strict = true
    ) : array {
        return [
            'type' => 'json_schema',
            'description' => $schemaDescription,
            'json_schema' => [
                'name' => $schemaName,
                'schema' => $this->toJsonSchema(),
                'strict' => $strict,
            ],
        ];
    }

    // MAPPING //////////////////////////////////////////////////////////////

    private function stringToArray() : array {
        $result = $this->prepare([
            'type' => 'string',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta);
        return $this->appendEnumValues($result);
    }

    private function boolToArray() : array {
        $result = $this->prepare([
            'type' => 'boolean',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta);
        return $this->appendEnumValues($result);
    }

    private function numberToArray() : array {
        $result = $this->prepare([
            'type' => 'number',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta);
        return $this->appendEnumValues($result);
    }

    private function integerToArray() : array {
        $result = $this->prepare([
            'type' => 'integer',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta);
        return $this->appendEnumValues($result);
    }

    private function objectToArray() : array {
        $properties = $this->propertiesAsArray();
        return $this->prepare([
            'type' => 'object',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
            'properties' => $properties === [] ? new \stdClass() : $properties,
            'required' => $this->requiredProperties,
            'additionalProperties' => $this->additionalProperties,
        ], $this->meta);
    }

    private function arrayToArray() : array {
        return $this->prepare([
            'type' => 'array',
            'nullable' => $this->nullable,
            'description' => $this->description,
            'title' => $this->title,
            'items' => $this->itemSchema?->toArray() ?? [],
        ], $this->meta);
    }

    public function anyToArray() : array {
        return ($this->prepare([
            //JsonSchemaType::JSON_ANY_OF,
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta));
    }

    private function refToArray() : array {
        return $this->prepare([
            '$ref' => $this->ref(),
            'description' => $this->description,
            'title' => $this->title,
        ], $this->meta);
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    private function propertiesAsArray() : array {
        $result = [];
        foreach ($this->properties ?? [] as $property) {
            $result[$property->name()] = $property->toArray();
        }
        return $result;
    }

    private function prepare(array $values, array $meta) : array {
        $result = $this->appendMeta($values, $meta);
        foreach ($result as $key => $value) {
            if ($value === null && $key !== 'default') {
                unset($result[$key]);
            }
            if (is_array($value) && empty($value) && $key !== 'default') {
                unset($result[$key]);
            }
            if (is_string($value) && $value === '' && $key !== 'default') {
                unset($result[$key]);
            }
        }
        return $result;
    }

    private function appendMeta(array $values, array $meta) : array {
        $result = [];
        foreach ($meta as $key => $value) {
            // if key does not start with 'x-', prepend it with 'x-'
            $key = match(true) {
                is_string($key) && $key === 'default' => 'default',
                is_string($key) && (strpos($key, 'x-') === 0) => $key,
                default => 'x-' . $key,
            };
            // if value is an object, convert it to an array
            $value = match(true) {
                is_object($value) && method_exists($value, 'toArray') => $value->toArray(),
                is_object($value) => (array) $value,
                default => $value,
            };
            $result[$key] = $value;
        }
        return array_merge($values, $result);
    }

    private function appendEnumValues(array $schema) : array {
        if (empty($this->enumValues)) {
            return $schema;
        }

        $schema['enum'] = $this->enumValues;
        return $schema;
    }

    private function appendDefinitions(array $schema) : array {
        if (!$this->hasDefs()) {
            return $schema;
        }

        $definitions = [];
        foreach ($this->defs() as $name => $definition) {
            $definitions[$name] = $definition->toArray();
        }

        if ($definitions === []) {
            return $schema;
        }

        $schema['$defs'] = $definitions;
        return $schema;
    }
}
