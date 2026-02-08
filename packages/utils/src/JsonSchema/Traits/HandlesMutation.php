<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;

trait HandlesMutation
{
    public function withName(string $name) : JsonSchema {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description) : JsonSchema {
        $this->description = $description;
        return $this;
    }

    public function withTitle(string $title) : JsonSchema {
        $this->title = $title;
        return $this;
    }

    public function withNullable(bool $nullable = true) : JsonSchema {
        $this->nullable = $nullable;
        return $this;
    }

    public function withMeta(array $meta = []) : JsonSchema {
        $this->meta = $meta;
        return $this;
    }

    public function withEnumValues(?array $enum = null) : JsonSchema {
        $this->enumValues = $enum;
        return $this;
    }

    public function withProperties(?array $properties) : JsonSchema {
        $this->properties = JsonSchema::toKeyedProperties($properties);
        return $this;
    }

    public function withItemSchema(?JsonSchema $itemSchema = null) : JsonSchema {
        $this->itemSchema = $itemSchema;
        return $this;
    }

    public function withRequiredProperties(?array $required = null) : JsonSchema {
        $this->requiredProperties = $required;
        return $this;
    }

    public function withAdditionalProperties(bool $additionalProperties = false) : JsonSchema {
        $this->additionalProperties = $additionalProperties;
        return $this;
    }
}
