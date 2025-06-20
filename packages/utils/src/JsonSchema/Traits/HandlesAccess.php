<?php

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;

trait HandlesAccess
{
    public function type() : string {
        return $this->type;
    }

    public function name() : string {
        return $this->name;
    }

    public function isNullable() : bool {
        return $this->nullable ?? false;
    }

    /**
     * @return array<string>
     */
    public function requiredProperties() : array {
        return $this->requiredProperties ?? [];
    }

    /**
     * @return JsonSchema[]
     */
    public function properties() : array {
        return $this->properties ?? [];
    }

    public function property(string $name) : ?JsonSchema {
        if ($this->isObject() && isset($this->properties[$name])) {
            return $this->properties[$name];
        }
        return null;
    }

    public function hasAdditionalProperties() : bool {
        return $this->additionalProperties ?? false;
    }

    public function additionalProperties() : ?bool {
        return $this->additionalProperties;
    }

    public function description() : string {
        return $this->description ?? '';
    }

    public function title() : string {
        return $this->title ?? '';
    }

    public function meta(string $key = null) : mixed {
        if ($key === null) {
            return $this->meta;
        }
        return $this->meta[$key] ?? null;
    }

    // TYPE SPECIFIC //////////////////////////////////////////////////////////

    /**
     * @return array<string>
     */
    public function enumValues() : array {
        return $this->enumValues ?? [];
    }

    public function hasEnumValues() : bool {
        return !empty($this->enumValues);
    }

    public function itemSchema() : ?JsonSchema {
        return $this->itemSchema ?? null;
    }

    public function hasItemSchema() : bool {
        return $this->itemSchema !== null;
    }

    public function itemType() : ?string {
        return $this->itemSchema?->type ?? null;
    }

    public function objectClass() : ?string {
        return $this->meta['php-class'] ?? null;
    }

    public function hasObjectClass() : bool {
        return isset($this->meta['php-class']);
    }

    // TYPE CHECKS ////////////////////////////////////////////////////////

    public function isMixed() : bool {
        return empty($this->type);
    }

    public function isObject() : bool {
        return $this->type === self::JSON_OBJECT;
    }

    public function isString() : bool {
        return $this->type === self::JSON_STRING;
    }

    public function isInteger() : bool {
        return $this->type === self::JSON_INTEGER;
    }

    public function isBoolean() : bool {
        return $this->type === self::JSON_BOOLEAN;
    }

    public function isNumber() : bool {
        return $this->type === self::JSON_NUMBER;
    }

    public function isNull() : bool {
        return $this->type === self::JSON_NULL;
    }

    public function isEnum() : bool {
        return !empty($this->enumValues)
            || (
                $this->hasObjectClass()
                && class_exists($this->objectClass())
                && is_subclass_of($this->objectClass(), \BackedEnum::class)
            );
    }

    public function isCollection() : bool {
        return $this->type === self::JSON_ARRAY
            && $this->hasItemSchema();
    }

    public function isArray() : bool {
        return $this->type === self::JSON_ARRAY
            && (!$this->hasItemSchema() || $this->itemSchema()?->isMixed());
    }

    public function isScalar() : bool {
        return in_array($this->type, self::JSON_SCALAR_TYPES);
    }
}