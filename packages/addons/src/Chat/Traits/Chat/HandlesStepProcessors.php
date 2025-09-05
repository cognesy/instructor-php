<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Traits\Chat;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ChatStepOutcome;
use Cognesy\Addons\Chat\Data\StepProcessors;
use Cognesy\Addons\Chat\Processors\Step\AccumulateTokenUsage;
use Cognesy\Addons\Chat\Processors\Step\UpdateStep;

trait HandlesStepProcessors
{
    public function withProcessors(CanProcessChatStep ...$processors): self {
        if (!($this->processors instanceof StepProcessors)) {
            $this->processors = new StepProcessors();
        }
        $this->processors->add(...$processors);
        return $this;
    }

    public function withDefaultProcessors(): self {
        return $this->withProcessors(
            new AccumulateTokenUsage(),
            new UpdateStep(),
        );
    }

    protected function processStep(ChatStep $step, ChatState $state): ChatStepOutcome {
        return $this->processors->apply($step, $state);
    }
}
