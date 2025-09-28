<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Contracts;

interface ObservationPolicy
{
    public function shouldObserve(string $signatureId, string $predictorPath, string $class): bool;

    public function sampleRate(string $signatureId): float; // 0..1
}

