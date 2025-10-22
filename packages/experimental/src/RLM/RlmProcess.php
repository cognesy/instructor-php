<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StepByStep;
use Cognesy\Experimental\RLM\Contracts\ReplEnvironment;
use Cognesy\Experimental\RLM\Contracts\Toolset;
use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;
use Cognesy\Experimental\RLM\Drivers\StrictRlmDriver;
use Cognesy\Experimental\RLM\State\RlmState;
use Cognesy\Experimental\RLM\Steps\RlmStep;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class RlmProcess extends StepByStep
{
    private StrictRlmDriver $driver;
    private Toolset $tools;
    private ReplEnvironment $repl;
    private ContinuationCriteria $criteria;

    public function __construct(
        StrictRlmDriver $driver,
        Toolset $tools,
        ReplEnvironment $repl,
        ?CanApplyProcessors $processors,
        ContinuationCriteria $criteria,
    ) {
        parent::__construct($processors);
        $this->driver = $driver;
        $this->tools = $tools;
        $this->repl = $repl;
        $this->criteria = $criteria;
    }

    // Template methods ///////////////////////////////////////

    protected function canContinue(object $state): bool {
        assert($state instanceof RlmState);
        return $this->criteria->canContinue($state);
    }

    protected function makeNextStep(object $state): object {
        assert($state instanceof RlmState);
        $pending = $this->driver->extractAction($state->messages(), $this->repl->inventory(), $state->policy());
        $structured = $pending->get();
        $usage = $pending->response()->usage();

        $actionArr = $structured->toArray();
        $assistantMsg = Message::asAssistant('Action decided');
        $step = new RlmStep(
            inputMessages: $state->messages(),
            outputMessages: Messages::empty()->appendMessage($assistantMsg),
            usage: $usage,
            action: is_array($actionArr) ? $actionArr : [],
        );
        return $step;
    }

    protected function applyStep(object $state, object $nextStep): object {
        assert($state instanceof RlmState && $nextStep instanceof RlmStep);
        $payload = $nextStep->action();
        $type = (string)($payload['type'] ?? '');

        return match ($type) {
            'tool' => $this->onTool($state, $nextStep, $payload),
            'write' => $this->onWrite($state, $nextStep, $payload),
            'final' => $this->onFinal($state, $nextStep, $payload),
            'await' => $this->onAwait($state, $nextStep, $payload),
            default => $state
                ->withAddedStep($nextStep)
                ->withCurrentStep($nextStep)
                ->withAccumulatedUsage($nextStep->usage()),
        };
    }

    protected function onNoNextStep(object $state): object {
        assert($state instanceof RlmState);
        return $state;
    }

    protected function onStepCompleted(object $state): object {
        assert($state instanceof RlmState);
        return $state;
    }

    protected function onFailure(\Throwable $error, object $state): object {
        assert($state instanceof RlmState);
        $msg = Message::asUser('ERROR: ' . $error->getMessage());
        $step = new RlmStep(inputMessages: $state->messages(), outputMessages: Messages::empty()->appendMessage($msg));
        return $state
            ->withAddedStep($step)
            ->withCurrentStep($step);
    }

    // Action handlers /////////////////////////////////////////

    /**
     * @param array<string,mixed> $payload
     */
    private function onTool(RlmState $state, RlmStep $nextStep, array $payload): RlmState {
        $name = (string)($payload['name'] ?? '');
        $args = is_array($payload['args'] ?? null) ? $payload['args'] : [];
        $handle = $this->tools->call($name, $args);
        $msg = Message::asAssistant('Tool "' . $name . '" executed → [stored: ' . $handle->id() . ']');
        $step = RlmStep::from($nextStep->inputMessages(), Messages::empty()->appendMessage($msg), $nextStep->usage(), $payload);
        return $state
            ->withAddedStep($step)
            ->withCurrentStep($step)
            ->withAccumulatedUsage($step->usage());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function onWrite(RlmState $state, RlmStep $nextStep, array $payload): RlmState {
        $var = (string)($payload['var'] ?? '');
        $from = (string)($payload['from'] ?? '');
        $this->repl->writeVar($var, ['handle' => $from]);
        $state = $state->withMessages($state->messages()->appendMessage(Message::asAssistant('WRITE ' . $var . ' <= ' . $from)));
        return $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep)
            ->withAccumulatedUsage($nextStep->usage());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function onFinal(RlmState $state, RlmStep $nextStep, array $payload): RlmState {
        $from = (string)($payload['from'] ?? '');
        $handle = ResultHandle::from($from);
        $msg = Message::asAssistant('FINAL → ' . $handle->id());
        $step = RlmStep::from($nextStep->inputMessages(), Messages::empty()->appendMessage($msg), $nextStep->usage(), $payload);
        return $state
            ->withAddedStep($step)
            ->withCurrentStep($step)
            ->withAccumulatedUsage($step->usage())
            ->with(finalHandle: $handle, terminal: true);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function onAwait(RlmState $state, RlmStep $nextStep, array $payload): RlmState {
        $reason = (string)($payload['reason'] ?? 'await');
        $msg = Message::asAssistant('AWAIT → ' . $reason);
        $step = RlmStep::from($nextStep->inputMessages(), Messages::empty()->appendMessage($msg), $nextStep->usage(), $payload);
        return $state
            ->withAddedStep($step)
            ->withCurrentStep($step)
            ->withAccumulatedUsage($step->usage())
            ->with(terminal: true);
    }
}
