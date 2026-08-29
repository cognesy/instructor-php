<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Redacted structural description: no factories, instances, paths, or secrets. */
final readonly class TellHostDescription
{
    /**
     * @param  list<array{id: string, description: string, provides: list<class-string>, requires: list<class-string>, optional: list<class-string>}>  $modules
     * @param  list<class-string>  $requiredCapabilities
     */
    public function __construct(
        public string $profile,
        public array $modules,
        public array $requiredCapabilities,
    ) {}

    /** @return array{profile: string, modules: list<array{id: string, description: string, provides: list<class-string>, requires: list<class-string>, optional: list<class-string>}>, requiredCapabilities: list<class-string>} */
    public function toArray(): array {
        return [
            'profile' => $this->profile,
            'modules' => $this->modules,
            'requiredCapabilities' => $this->requiredCapabilities,
        ];
    }
}
