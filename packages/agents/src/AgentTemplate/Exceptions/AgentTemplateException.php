<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Exceptions;

use RuntimeException;
use Throwable;

final class AgentTemplateException extends RuntimeException
{
    public static function blueprintNotFound(string $name, array $available = []): self
    {
        $availableStr = $available !== [] ? ' Available: ' . implode(', ', $available) : '';
        return new self("Blueprint '{$name}' not found.{$availableStr}");
    }

    public static function invalidBlueprint(string $name, string $reason): self
    {
        return new self("Blueprint '{$name}' is invalid: {$reason}");
    }

    public static function blueprintMissing(string $definitionId): self
    {
        return new self("Agent definition '{$definitionId}' must specify 'blueprint' or 'blueprint_class'.");
    }

    public static function blueprintCreationFailed(string $class, string $id, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to create agent from blueprint '{$class}' for definition '{$id}'.",
            previous: $previous,
        );
    }
}
