<?php declare(strict_types=1);

namespace Cognesy\Pipeline\StateContracts;

use Cognesy\Utils\TagMap\Contracts\CanCarryTags;

interface CanCarryState extends CanCarryTags, CanCarryResult
{
    public function applyTo(CanCarryState $priorState) : CanCarryState;
}