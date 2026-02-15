<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook\Hooks;

use Closure;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

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
