<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use InvalidArgumentException;

/** Named immutable module graph plus its mandatory public capabilities. */
final readonly class TellHostProfile
{
    /**
     * @param  list<TellModuleDefinition>  $modules
     * @param  list<class-string>  $requiredCapabilities
     */
    public function __construct(
        public string $name,
        public array $modules,
        public array $requiredCapabilities = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $this->name) !== 1) {
            throw new InvalidArgumentException("Invalid Tell host profile name: {$this->name}");
        }
    }

    public static function empty(string $name = 'custom'): self {
        return new self($name, []);
    }
}
