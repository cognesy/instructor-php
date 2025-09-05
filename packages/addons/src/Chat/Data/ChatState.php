<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Script;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

final class ChatState
{
    public function __construct(
        private readonly Script $script = new Script(),
        private readonly Participants $participants = new Participants(),
        private readonly array $variables = [],
        private readonly ChatSteps $steps = new ChatSteps(),
        private readonly ?ChatStep $currentStep = null,
        private readonly Usage $usage = new Usage(),
        private readonly DateTimeImmutable $startedAt = new DateTimeImmutable(),
    ) {}

    // HANDLE SCRIPT //////////////////////////////////////////////
    public function script() : Script { return $this->script; }
    public function withScript(Script $script) : self {
        return new self(
            script: $script,
            participants: $this->participants,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }
    public function messages(array $sectionOrder = []) : Messages {
        return $this->script->select($sectionOrder ?: [])->toMessages();
    }

    // HANDLE PARTICIPANTS ////////////////////////////////////////
    public function participants() : Participants { return $this->participants; }
    public function withParticipants(CanParticipateInChat ...$p) : self {
        return new self(
            script: $this->script,
            participants: (new Participants())->add(...$p),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    // HANDLE VARIABLES ///////////////////////////////////////////
    public function withVariable(int|string $name, mixed $value) : self {
        $vars = $this->variables;
        $vars[$name] = $value;
        return new self(
            script: $this->script,
            participants: $this->participants,
            variables: $vars,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }
    public function variable(string $name, mixed $default = null) : mixed { return $this->variables[$name] ?? $default; }
    public function variables() : array { return $this->variables; }

    // HANDLE STEPS ///////////////////////////////////////////////
    public function currentStep() : ?ChatStep { return $this->currentStep; }
    public function steps() : ChatSteps { return $this->steps; }
    public function stepCount() : int { return $this->steps->count(); }
    public function withAddedStep(ChatStep $step) : self {
        $newSteps = $this->steps->add($step);
        return new self(
            script: $this->script,
            participants: $this->participants,
            variables: $this->variables,
            steps: $newSteps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }
    public function withCurrentStep(ChatStep $step) : self {
        return new self(
            script: $this->script,
            participants: $this->participants,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    // HANDLE USAGE ///////////////////////////////////////////////
    public function usage() : Usage { return $this->usage; }
    public function withUsage(Usage $usage) : self {
        return new self(
            script: $this->script,
            participants: $this->participants,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $usage,
            startedAt: $this->startedAt,
        );
    }
    public function accumulateUsage(Usage $usage) : self {
        $u = $this->usage->clone();
        $u->accumulate($usage);
        return $this->withUsage($u);
    }

    // HANDLE TIMING //////////////////////////////////////////////
    public function startedAt() : DateTimeImmutable { return $this->startedAt; }
}
