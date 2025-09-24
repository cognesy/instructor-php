<?php declare(strict_types=1);

namespace Cognesy\Pipeline\StateContracts;

interface CanCarryState extends CanCarryTags, CanCarryResult
{
    public function applyTo(CanCarryState $priorState) : CanCarryState;
}