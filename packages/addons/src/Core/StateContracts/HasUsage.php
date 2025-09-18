<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StateContracts;

use Cognesy\Polyglot\Inference\Data\Usage;

interface HasUsage
{
    public function usage(): Usage;
    public function withUsage(Usage $usage): static;
}