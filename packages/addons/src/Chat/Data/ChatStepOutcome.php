<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

final class ChatStepOutcome
{
    public function __construct(
        private readonly ChatStep $step,
        private readonly ChatState $state,
    ) {}

    public function step() : ChatStep { return $this->step; }
    public function state() : ChatState { return $this->state; }
}

