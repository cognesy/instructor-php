<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Collaborators;

use Closure;
use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Contracts\CanRespondWithMessages;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Events\CollaborationResponseRequested;
use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final class ExternalCollaborator implements CanCollaborate
{
    private CanRespondWithMessages $provider;
    private CanCompileMessages $compiler;
    private CanHandleEvents $events;

    /**
     * @param callable(CollaborationState): (Messages|Message|array)|CanRespondWithMessages|null $provider
     */
    public function __construct(
        private readonly string $name = 'external',
        CanRespondWithMessages|callable|null $provider = null,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->provider = $this->makeProvider($provider);
        $this->compiler = $compiler ?? new SelectedSections(['summary', 'buffer', 'messages']);
        $this->events = $events ?? EventBusResolver::using($events);
    }

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function act(CollaborationState $state): CollaborationStep {
        $this->emitChatResponseRequested($this->provider, $state);
        $response = $this->provider->respond($state);
        $this->emitChatResponseReceived($response);

        return new CollaborationStep(
            collaboratorName: $this->name,
            inputMessages: $this->compiler->compile($state),
            outputMessages: $response,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////

    /**
     * @param callable(CollaborationState): (Messages|Message|array)|CanRespondWithMessages|null $provider
     */
    private function makeProvider(callable|CanRespondWithMessages|null $provider): CanRespondWithMessages {
        return match (true) {
            $provider instanceof CanRespondWithMessages => $provider,
            is_callable($provider) => new class(Closure::fromCallable($provider)) implements CanRespondWithMessages {
                /**
                 * @param Closure(CollaborationState): (Messages|Message|array) $provider
                 */
                public function __construct(private readonly Closure $provider) {}

                #[\Override]
                public function respond(CollaborationState $state): Messages {
                    return Messages::fromAny(($this->provider)($state));
                }
            },
            default => new class implements CanRespondWithMessages {
                #[\Override]
                public function respond(CollaborationState $state): Messages {
                    return new Messages(new Message(role: 'user', content: ''));
                }
            },
        };
    }

    private function emitChatResponseRequested(CanRespondWithMessages $provider, CollaborationState $state): void {
        $this->events->dispatch(new CollaborationResponseRequested([
            'participant' => $this->name,
            'provider' => get_class($provider),
            'state' => $state->toArray(),
        ]));
    }

    private function emitChatResponseReceived(Messages $outputMessages): void {
        $this->events->dispatch(new CollaborationResponseRequested([
            'participant' => $this->name,
            'outputMessages' => $outputMessages->toArray(),
        ]));
    }
}
