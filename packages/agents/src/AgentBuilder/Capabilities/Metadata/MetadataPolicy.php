<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

/**
 * Configuration for metadata storage limits and behavior.
 */
final readonly class MetadataPolicy
{
    public function __construct(
        public int $maxKeys = 50,
        public int $maxValueSizeBytes = 65536,
        public array $reservedKeys = ['tasks', 'skills', 'self_critic'],
    ) {}

    public function isReservedKey(string $key): bool {
        return in_array($key, $this->reservedKeys, true);
    }
}
