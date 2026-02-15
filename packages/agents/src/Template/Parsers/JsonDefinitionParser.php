<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Parsers;

use Cognesy\Agents\Template\Data\AgentDefinition;
use InvalidArgumentException;
use JsonException;

final readonly class JsonDefinitionParser implements CanParseAgentDefinition
{
    #[\Override]
    public function parse(mixed $data): AgentDefinition {
        $array = match (true) {
            is_array($data) => $data,
            is_string($data) => $this->parseJson($data),
            default => throw new InvalidArgumentException('JSON agent definition must be a string or array.'),
        };

        return AgentDefinition::fromArray($array);
    }

    /** @return array<string, mixed> */
    private function parseJson(string $json): array {
        try {
            $parsed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Invalid JSON: {$e->getMessage()}", previous: $e);
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("Invalid JSON: agent definition must be an object");
        }

        return $parsed;
    }
}

