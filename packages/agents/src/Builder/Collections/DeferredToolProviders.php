<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Collections;

use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Builder\Data\ResolvedTools;
use Cognesy\Agents\Collections\NameList;
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
        return $this->resolveWithProvenance($tools, $driver, $events)->tools();
    }

    public function resolveWithProvenance(Tools $tools, CanUseTools $driver, CanHandleEvents $events): ResolvedTools {
        $resolvedTools = $tools;
        $deferredNames = new NameList();
        foreach ($this->providers as $provider) {
            $context = new DeferredToolContext($resolvedTools, $driver, $events);
            $provided = $provider->provideTools($context);
            $deferredNames = $deferredNames->merge(new NameList(...$provided->names()));
            $resolvedTools = $resolvedTools->merge($provided);
        }
        return new ResolvedTools($resolvedTools, $deferredNames);
    }
}
