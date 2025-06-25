<?php

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchemaType;

trait HandlesAccess
{
    public function type() : JsonSchemaType {
        return $this->type;
    }

    private function hasType() : bool {
        return !empty($this->type) && !$this->type->isNull();
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

    public function meta(string $key = null, mixed $default = null) : mixed {
        if ($key === null) {
            return $this->meta;
        }
        return $this->meta[$key] ?? $default;
    }

    public function hasMeta(string $key) : bool {
        return isset($this->meta[$key]);
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

    public function itemType() : ?JsonSchemaType {
        return $this->itemSchema?->type ?? null;
    }

    public function hasItemType() : bool {
        return $this->itemSchema !== null && $this->itemSchema->hasType();
    }

    public function objectClass() : ?string {
        return $this->meta('php-class', null);
    }

    public function hasObjectClass() : bool {
        return $this->hasMeta('php-class')
            && !empty($this->meta('php-class'));
    }

    // TYPE CHECKS ////////////////////////////////////////////////////////

    public function isAny() : bool {
        return $this->type->isAny();
    }

    public function isObject() : bool {
        return $this->type->isObject();
    }

    public function isString() : bool {
        return $this->type->isString();
    }

    public function isInteger() : bool {
        return $this->type->isInteger();
    }

    public function isBoolean() : bool {
        return $this->type->isBoolean();
    }

    public function isNumber() : bool {
        return $this->type->isNumber();
    }

    public function isNull() : bool {
        return $this->type->isNull();
    }

    public function isEnum() : bool {
        return !empty($this->enumValues)
            || (
                $this->hasObjectClass()
                && class_exists($this->objectClass())
                && is_subclass_of($this->objectClass(), \BackedEnum::class)
            );
    }

    public function isOption() : bool {
        return $this->type->isString()
            && $this->hasEnumValues()
            && !$this->hasObjectClass();
    }

    public function isArray() : bool {
        return $this->type->isArray()
            && (
                !$this->hasItemSchema()
                || $this->itemSchema()?->isAny()
            );
    }

    public function isCollection() : bool {
        return $this->type->isArray()
            && $this->hasItemSchema()
            && !$this->itemSchema?->isAny();
    }

    public function isScalarCollection() : bool {
        return $this->type->isArray()
            && $this->hasItemType()
            && $this->itemSchema?->isScalar();
    }

    public function isEnumCollection() : bool {
        return $this->type->isArray()
            && $this->hasItemSchema()
            && $this->itemSchema?->isEnum();
    }

    public function isObjectCollection() : bool {
        return $this->type->isArray()
            && $this->hasItemSchema()
            && $this->itemSchema?->isObject();
    }

    public function isOptionCollection() : bool {
        return $this->type->isArray()
            && $this->hasItemSchema()
            && $this->itemSchema?->isOption();
    }

    public function isScalar() : bool {
        return in_array($this->type, JsonSchemaType::JSON_SCALAR_TYPES)
            && !$this->hasEnumValues();
    }
}