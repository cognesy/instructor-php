<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Closure;
use Cognesy\Addons\Chat\Contracts\CanDecideToContinueChat;
use Cognesy\Addons\Chat\Data\ChatState;

class ResponseContentCheck implements CanDecideToContinueChat
{
    private Closure $decideIfContinue;

    public function __construct(callable $decideIfContinue) {
        $this->decideIfContinue = Closure::fromCallable($decideIfContinue);
    }

    public function canContinue(ChatState $state): bool {
        $response = $state->currentStep()?->outputMessage();
        if ($response === null) {
            return true;
        }
        return ($this->decideIfContinue)($response);
    }
}