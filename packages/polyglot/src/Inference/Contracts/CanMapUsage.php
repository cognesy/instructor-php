<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

interface CanMapUsage
{
    public function fromData(array $data): InferenceUsage;
}