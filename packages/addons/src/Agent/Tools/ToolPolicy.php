<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools;

final readonly class ToolPolicy
{
    /**
     * @param array<int, string>|null $allowlist
     * @param array<int, string>|null $denylist
     */
    public function __construct(
        public ?array $allowlist = null,
        public ?array $denylist = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function filterNames(array $candidateNames, array $availableNames): array
    {
        $available = array_flip($availableNames);
        $filtered = array_values(array_filter($candidateNames, static fn (string $name): bool => isset($available[$name])));

        if ($this->allowlist !== null && $this->allowlist !== []) {
            $allowed = array_flip($this->allowlist);
            $filtered = array_values(array_filter($filtered, static fn (string $name): bool => isset($allowed[$name])));
        }

        if ($this->denylist !== null && $this->denylist !== []) {
            $denied = array_flip($this->denylist);
            $filtered = array_values(array_filter($filtered, static fn (string $name): bool => !isset($denied[$name])));
        }

        return $filtered;
    }
}
