<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

class RegisteredHooks
{
    protected array $hooks = [];

    public function __construct(RegisteredHook ...$hooks) {
        $this->hooks = $this->sort($hooks);
    }

    public function withHook(RegisteredHook $hook): self {
        return new self(...array_merge($this->hooks, [$hook]));
    }

    /**
     * @return RegisteredHook[]
     */
    public function hooks(): array {
        return $this->hooks;
    }

    private function sort(array $hooks): array {
        usort($hooks, fn(RegisteredHook $a, RegisteredHook $b) => $a->compare($b));
        return $hooks;
    }
}