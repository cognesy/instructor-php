<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\Usage;

interface CanMapUsage
{
    public function fromData(array $data): Usage;
}