<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;

final readonly class ApplyCachedContextHook implements HookInterface
{
    public function __construct(
        private CachedInferenceContext $cachedContext,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state()->withCachedContext($this->cachedContext);
        return $context->withState($state);
    }
}
