<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Data;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;

final readonly class ResolvedTools
{
    public function __construct(
        private Tools $tools,
        private NameList $deferredNames,
    ) {}

    public function tools(): Tools {
        return $this->tools;
    }

    public function deferredNames(): NameList {
        return $this->deferredNames;
    }
}
