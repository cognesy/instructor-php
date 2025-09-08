<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Compilers\AllSections;
use Cognesy\Addons\Chat\Contracts\CanCompileMessages;
use Cognesy\Addons\Chat\Data\Collections\ChatSteps;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

final readonly class ChatState
{
    private const DEFAULT_SECTION = 'messages';

    public function __construct(
        private MessageStore $store = new MessageStore(),
        private array $variables = [],
        private ChatSteps $steps = new ChatSteps(),
        private ?ChatStep $currentStep = null,
        private Usage $usage = new Usage(),
        private DateTimeImmutable $startedAt = new DateTimeImmutable(),
        private CanCompileMessages $compiler = new AllSections(),
    ) {}

    public function messages(): Messages {
        return $this->store->getSection(self::DEFAULT_SECTION)?->messages()
            ?? Messages::empty();
    }

    public function compiledMessages(): Messages {
        return $this->compiler->compile($this);
    }

    public function store(): MessageStore {
        return $this->store;
    }

    public function variable(string $name, mixed $default = null): mixed {
        return $this->variables[$name] ?? $default;
    }

    public function variables(): array {
        return $this->variables;
    }

    public function currentStep(): ?ChatStep {
        return $this->currentStep;
    }

    public function steps(): ChatSteps {
        return $this->steps;
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    public function usage(): Usage {
        return $this->usage;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function accumulateUsage(Usage $usage): self {
        $u = $this->usage->clone();
        $u->accumulate($usage);
        return $this->withUsage($u);
    }

    public function withVariable(int|string $name, mixed $value): self {
        $vars = $this->variables;
        $vars[$name] = $value;
        return new self(
            store: $this->store,
            variables: $vars,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withAddedStep(ChatStep $step): self {
        $newSteps = $this->steps->add($step);
        return new self(
            store: $this->store,
            variables: $this->variables,
            steps: $newSteps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withCurrentStep(ChatStep $step): self {
        return new self(
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $step,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withUsage(Usage $usage): self {
        return new self(
            store: $this->store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $usage,
            startedAt: $this->startedAt,
        );
    }

    public function withMessages(Messages $messages) : self {
        return new self(
            store: $this->store->withSectionMessages(self::DEFAULT_SECTION, $messages),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withSectionMessages(string $section, Messages $messages) : self {
        return new self(
            store: $this->store->withSectionMessages($section, $messages),
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function withMessageStore(MessageStore $store) : self {
        return new self(
            store: $store,
            variables: $this->variables,
            steps: $this->steps,
            currentStep: $this->currentStep,
            usage: $this->usage,
            startedAt: $this->startedAt,
        );
    }

    public function toArray() : array {
        return [
            'messages' => $this->store->toArray(),
            'variables' => $this->variables,
            'steps' => array_map(fn(ChatStep $s) => $s->toArray(), $this->steps->all()),
            'currentStep' => $this->currentStep?->toArray(),
            'usage' => $this->usage->toArray(),
            'startedAt' => $this->startedAt->format(DATE_ATOM),
        ];
    }
}
