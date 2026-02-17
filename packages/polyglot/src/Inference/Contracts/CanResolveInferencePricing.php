<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\Pricing;

interface CanResolveInferencePricing
{
    public function resolvePricing(InferenceRequest $request): ?Pricing;
}

