<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Steps;

use Cognesy\Addons\StepByStep\Step\Contracts\HasStepInfo;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepMessages;
use Cognesy\Addons\StepByStep\Step\Contracts\HasStepUsage;
use Cognesy\Addons\StepByStep\Step\StepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepInfo;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepMessages;
use Cognesy\Addons\StepByStep\Step\Traits\HandlesStepUsage;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;

final readonly class RlmStep implements HasStepInfo, HasStepMessages, HasStepUsage
{
    use HandlesStepInfo;
    use HandlesStepMessages;
    use HandlesStepUsage;

    /** @var array<string,mixed> */
    private array $action;

    public function __construct(
        ?StepInfo $stepInfo = null,
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?Usage $usage = null,
        array $action = [],
    ) {
        $this->stepInfo = $stepInfo ?? StepInfo::new();
        $this->inputMessages = $inputMessages ?? Messages::empty();
        $this->outputMessages = $outputMessages ?? Messages::empty();
        $this->usage = $usage ?? new Usage();
        $this->action = $action;
    }

    public static function from(Messages $input, Messages $output, Usage $usage, array $action = []): self
    {
        return new self(
            stepInfo: StepInfo::new(),
            inputMessages: $input,
            outputMessages: $output,
            usage: $usage,
            action: $action,
        );
    }

    /** @return array<string,mixed> */
    public function action(): array { return $this->action; }

    public function toArray(): array
    {
        return [
            'stepInfo' => $this->stepInfo->toArray(),
            'input' => $this->inputMessages->toArray(),
            'output' => $this->outputMessages->toArray(),
            'usage' => $this->usage->toArray(),
            'action' => $this->action,
        ];
    }
}
