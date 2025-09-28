<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\ObservationPolicy;

final class DefaultObservationPolicy implements ObservationPolicy
{
    public function __construct(private float $defaultSample = 0.0) {}

    public function shouldObserve(string $signatureId, string $predictorPath, string $class): bool {
        return $this->defaultSample > 0.0;
    }

    public function sampleRate(string $signatureId): float {
        return $this->defaultSample;
    }
}