<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Closure;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;

final readonly class CallableHook implements HookInterface
{
    /** @var Closure(HookContext): HookContext */
    private Closure $callback;

    /**
     * @param callable(HookContext): HookContext $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        return ($this->callback)($context);
    }
}
