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
     * @return JsonSchema[]|null
     */
    public function properties() : array {
        return $this->properties ?? [];
    }

    public function property(string $name) : ?JsonSchema {
        return $this->properties[$name] ?? null;
    }

    public function itemSchema() : ?JsonSchema {
        return $this->itemSchema ?? null;
    }

    /**
     * @return array<string>
     */
    public function enumValues() : array {
        return $this->enumValues ?? [];
    }

    public function hasAdditionalProperties() : bool {
        return $this->additionalProperties ?? false;
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
}