<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Hooks;

final readonly class HookRegistration
{
    public function __construct(
        public Hook $hook,
        public int $priority,
        public int $order,
    ) {}
}
