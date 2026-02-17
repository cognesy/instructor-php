<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Pricing;

use Cognesy\Polyglot\Inference\Contracts\CanResolveInferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\Pricing;

final readonly class StaticPricingResolver implements CanResolveInferencePricing
{
    public function __construct(
        private ?Pricing $pricing = null,
    ) {}

    #[\Override]
    public function resolvePricing(InferenceRequest $request): ?Pricing {
        if ($this->pricing === null) {
            return null;
        }

        return $this->pricing->hasAnyPricing()
            ? $this->pricing
            : null;
    }
}

