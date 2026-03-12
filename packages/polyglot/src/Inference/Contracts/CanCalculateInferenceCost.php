<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Pricing\Cost;

/**
 * Strategy for calculating cost from inference usage and pricing rates.
 */
interface CanCalculateInferenceCost
{
    public function calculate(InferenceUsage $usage, InferencePricing $pricing): Cost;
}
