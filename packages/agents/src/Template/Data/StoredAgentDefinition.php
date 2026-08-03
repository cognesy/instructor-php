<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Data;

final readonly class StoredAgentDefinition
{
    public function __construct(
        public string $path,
        public bool $replaced,
    ) {}
}
