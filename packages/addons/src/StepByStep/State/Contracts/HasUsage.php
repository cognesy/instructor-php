<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

interface HasUsage
{
    public function usage(): InferenceUsage;
    public function withUsage(InferenceUsage $usage): static;
    public function withAccumulatedUsage(InferenceUsage $usage): static;
}