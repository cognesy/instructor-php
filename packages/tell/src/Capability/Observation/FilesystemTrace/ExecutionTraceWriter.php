<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\FilesystemTrace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Events\Event;
use Cognesy\Tell\Core\Configuration\TellConfig;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Core\Contract\Observation\CanRecordTellTrace;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;
use Cognesy\Tell\Data\TellTraceStatus;
use DateTimeZone;
use Throwable;

final class ExecutionTraceWriter implements CanRecordTellTrace
{
    private ?string $path = null;
    private ?string $executionId = null;
    private bool $failed = false;
    private bool $outcomeRecorded = false;
    private readonly TellEventNormalizer $events;

    public function __construct(
        private readonly TellPaths $paths,
        private readonly TellConfig $config,
        private readonly TellRequest $options,
    ) {
        $this->events = new TellEventNormalizer(
            branch: $options->branch,
            session: $options->session,
        );
    }

    public function attach(AgentLoop $loop): void {
        $loop->wiretap(function (object $event): void {
            $this->record($event);
        });
    }

    #[\Override]
    public function recordOutcome(
        TellTermination $termination,
        TellPublication $publication,
        TellExecutionMode $requestedMode,
    ): void {
        if ($this->outcomeRecorded || $this->failed) {
            return;
        }
        $this->outcomeRecorded = true;
        $this->executionId = $termination->executionId;
        if ($this->path === null) {
            $this->failed = true;

            return;
        }
        $context = $termination->toArray()['context'];
        $error = $termination->errors->all()[0] ?? null;
        $event = $this->events()->terminal($termination->status->value, [
            'steps' => $termination->stepCount,
            'reason' => $termination->stopSignal?->reason->value,
            'source' => $termination->stopSignal?->source,
            'inputTokens' => $termination->usage->inputTokens,
            'outputTokens' => $termination->usage->outputTokens,
            'errorCount' => $termination->errors->count(),
            'errorCode' => $error?->code,
            'errorCategory' => $error?->category,
            'errorPhase' => $error?->phase,
            'requestedMode' => $requestedMode->value,
            'publication' => $publication->status->value,
            'baseHead' => $publication->baseHead,
            'publishedHead' => $publication->publishedHead,
            ...$context,
        ]);
        if (!$this->write($this->path, $event)) {
            return;
        }
    }

    #[\Override]
    public function reference(): TellTraceReference {
        return new TellTraceReference(
            executionId: $this->executionId,
            status: match (true) {
                $this->failed => TellTraceStatus::Failed,
                $this->path === null => TellTraceStatus::Pending,
                default => TellTraceStatus::Written,
            },
            storageKind: $this->options->session === null ? 'execution' : 'session',
            path: $this->path,
        );
    }

    private function record(object $event): void {
        if ($this->failed || !$event instanceof Event || $event instanceof AgentExecutionCompleted) {
            return;
        }
        try {
            $this->startTrace($event);
            if ($this->path === null) {
                return;
            }
            $this->write($this->path, $this->eventPayload($event));
        } catch (Throwable) {
            $this->failed = true;
        }
    }

    private function startTrace(Event $event): void {
        if ($this->path !== null || !$event instanceof AgentExecutionStarted) {
            return;
        }
        $this->executionId = $event->executionId;
        if ($this->options->session !== null) {
            $this->path = $this->sessionTracePath($this->options->session);

            return;
        }
        $utc = $event->createdAt->setTimezone(new DateTimeZone('UTC'));
        $directory = (new TraceStorage($this->paths))->ensureTraceDate($utc->format('Y-m-d'));
        $executionId = preg_replace('/[^A-Za-z0-9._-]/', '_', $event->executionId) ?? 'unknown';
        $this->path = $directory . DIRECTORY_SEPARATOR . $executionId . '.jsonl';
    }

    private function sessionTracePath(string $session): string {
        $directory = (new TraceStorage($this->paths))->ensureSessionTraces();
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $session) ?? 'session';
        $slug = substr($safe, 0, 80);
        $hash = substr(hash('sha256', $session), 0, 12);

        return $directory . DIRECTORY_SEPARATOR . $slug . '-' . $hash . '.jsonl';
    }

    private function events(): TellEventNormalizer {
        return $this->events;
    }

    /** @param array<string, mixed> $record */
    private function write(string $path, array $record): bool {
        try {
            $line = json_encode(
                $record,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ) . "\n";
            $created = !is_file($path);
            if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
                $this->failed = true;

                return false;
            }
            if ($created) {
                @chmod($path, 0600);
            }

            return true;
        } catch (Throwable) {
            $this->failed = true;

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function eventPayload(Event $event): array {
        $envelope = $this->events()->normalize($event);
        if (!$this->config->includePayloads) {
            return $envelope;
        }

        $envelope['payload'] = TracePayload::sanitize(
            $event->data,
            includePayloads: true,
            maxStringLength: $this->config->maxStringLength,
        );

        return $envelope;
    }
}
