<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Support;

/**
 * Rewrites a JSON Schema into the subset accepted by OpenAI strict structured outputs:
 * every object lists all of its properties as required, and the OpenAPI-style
 * `nullable: true` keyword (ignored by strict mode) becomes an explicit `null` type.
 */
final class StrictJsonSchema
{
    private const NESTED_MAPS = ['properties', '$defs', 'definitions'];
    private const NESTED_LISTS = ['anyOf', 'oneOf', 'allOf', 'prefixItems'];
    private const NESTED_SCHEMAS = ['items', 'additionalProperties'];

    public static function normalize(array $schema): array {
        foreach (self::NESTED_MAPS as $key) {
            $schema = self::mapNested($schema, $key, preserveKeys: true);
        }
        foreach (self::NESTED_LISTS as $key) {
            $schema = self::mapNested($schema, $key, preserveKeys: false);
        }
        foreach (self::NESTED_SCHEMAS as $key) {
            $schema = match (is_array($schema[$key] ?? null)) {
                true => [...$schema, $key => self::normalize($schema[$key])],
                false => $schema,
            };
        }
        if (is_array($schema['properties'] ?? null)) {
            $schema['required'] = array_keys($schema['properties']);
        }

        return self::withExplicitNull($schema);
    }

    private static function mapNested(array $schema, string $key, bool $preserveKeys): array {
        $nested = $schema[$key] ?? null;
        if (!is_array($nested)) {
            return $schema;
        }
        $mapped = array_map(
            static fn (mixed $child): mixed => is_array($child) ? self::normalize($child) : $child,
            $nested,
        );
        $schema[$key] = $preserveKeys ? $mapped : array_values($mapped);

        return $schema;
    }

    private static function withExplicitNull(array $schema): array {
        if (($schema['nullable'] ?? null) !== true) {
            return $schema;
        }
        unset($schema['nullable']);
        $type = $schema['type'] ?? null;

        return match (true) {
            is_string($type) => self::withNullEnum([...$schema, 'type' => [$type, 'null']]),
            is_array($type) => self::withNullEnum([...$schema, 'type' => array_values(array_unique([...$type, 'null']))]),
            default => ['anyOf' => [$schema, ['type' => 'null']]],
        };
    }

    private static function withNullEnum(array $schema): array {
        $enum = $schema['enum'] ?? null;

        if (!is_array($enum) || in_array(null, $enum, true)) {
            return $schema;
        }

        return [...$schema, 'enum' => [...$enum, null]];
    }
}
