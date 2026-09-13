<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Observation;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;

/** Publishes one post-publication terminal event to Tell observers. */
final class TellExecutionEventStream
{
    private readonly TellEventNormalizer $normalizer;
    private bool $settled = false;

    /**
     * @param list<Closure(TellEventEnvelope): void> $listeners
     */
    public function __construct(
        private readonly TellExecutionMode $mode,
        private readonly string $agent,
        private readonly ?CanObserveTellExecution $observer,
        private readonly array $listeners,
        ?string $branch = null,
        ?string $session = null,
    ) {
        $this->normalizer = new TellEventNormalizer($branch, $session);
    }

    public function attach(AgentLoop $loop): void {
        $loop->wiretap(function (object $event): void {
            if ($event instanceof AgentExecutionCompleted) {
                return;
            }
            $this->emit($this->normalizer->normalize($event));
        });
    }

    public function settle(TellTermination $termination, TellPublication $publication): void {
        if ($this->settled) {
            return;
        }
        $this->settled = true;
        $context = $termination->toArray()['context'];
        $this->emit($this->normalizer->terminal($termination->status->value, [
            'reason' => $termination->stopSignal?->reason->value,
            'source' => $termination->stopSignal?->source,
            'steps' => $termination->stepCount,
            'inputTokens' => $termination->usage->inputTokens,
            'outputTokens' => $termination->usage->outputTokens,
            'publication' => $publication->status->value,
            ...$context,
        ]));
    }

    /** @param array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string} $normalized */
    private function emit(array $normalized): void {
        $event = TellEventEnvelope::fromNormalized($normalized, $this->mode, $this->agent);
        $this->observer?->observe($event);
        foreach ($this->listeners as $listener) {
            $listener($event);
        }
    }
}
