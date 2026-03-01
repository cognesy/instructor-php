<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema\Traits;

use Cognesy\Utils\JsonSchema\JsonSchema;

trait HandlesMutation
{
    public function withName(string $name) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($name): void {
            $schema->name = $name;
        });
    }

    public function withDescription(string $description) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($description): void {
            $schema->description = $description;
        });
    }

    public function withTitle(string $title) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($title): void {
            $schema->title = $title;
        });
    }

    public function withNullable(bool $nullable = true) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($nullable): void {
            $schema->nullable = $nullable;
        });
    }

    public function withMeta(array $meta = []) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($meta): void {
            $schema->meta = $meta;
        });
    }

    public function withEnumValues(?array $enum = null) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($enum): void {
            $schema->enumValues = $enum;
        });
    }

    public function withProperties(?array $properties) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($properties): void {
            $schema->properties = JsonSchema::toKeyedProperties($properties);
        });
    }

    public function withItemSchema(?JsonSchema $itemSchema = null) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($itemSchema): void {
            $schema->itemSchema = $itemSchema;
        });
    }

    public function withRequiredProperties(?array $required = null) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($required): void {
            $schema->requiredProperties = $required;
        });
    }

    public function withAdditionalProperties(?bool $additionalProperties = false) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($additionalProperties): void {
            $schema->additionalProperties = $additionalProperties;
        });
    }

    public function withRef(?string $ref = null) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($ref): void {
            $schema->ref = $ref;
        });
    }

    /** @param array<string, JsonSchema|array<string,mixed>>|null $defs */
    public function withDefs(?array $defs = null) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($defs): void {
            $schema->defs = JsonSchema::toKeyedDefs($defs);
        });
    }

    public function withDef(string $name, JsonSchema $definition) : JsonSchema {
        return $this->mutate(function (JsonSchema $schema) use ($name, $definition): void {
            $defs = $schema->defs ?? [];
            $defs[$name] = $definition;
            $schema->defs = $defs;
        });
    }

    /** @param callable(JsonSchema):void $mutator */
    private function mutate(callable $mutator) : JsonSchema {
        $copy = clone $this;
        $copy->rawSchema = null;
        $mutator($copy);
        return $copy;
    }
}
