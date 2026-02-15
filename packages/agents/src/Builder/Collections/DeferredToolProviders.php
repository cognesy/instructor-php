<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Collections;

use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Events\Contracts\CanHandleEvents;

final readonly class DeferredToolProviders
{
    /** @var list<CanProvideDeferredTools> */
    private array $providers;

    public function __construct(CanProvideDeferredTools ...$providers) {
        $this->providers = $providers;
    }

    public static function empty(): self {
        return new self();
    }

    public function withProvider(CanProvideDeferredTools $provider): self {
        return new self(...[...$this->providers, $provider]);
    }

    /** @return list<CanProvideDeferredTools> */
    public function providers(): array {
        return $this->providers;
    }

    public function resolve(Tools $tools, CanUseTools $driver, CanHandleEvents $events): Tools {
        $resolvedTools = $tools;
        foreach ($this->providers as $provider) {
            $context = new DeferredToolContext($resolvedTools, $driver, $events);
            $resolvedTools = $resolvedTools->merge($provider->provideTools($context));
        }
        return $resolvedTools;
    }
}

