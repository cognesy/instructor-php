<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Events\Event;
use Cognesy\Tell\Configuration\TellConfig;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Configuration\TellStorage;
use Cognesy\Tell\Console\TellOptions;
use DateTimeZone;
use Throwable;

final class ExecutionTraceWriter
{
    private ?string $path = null;
    private bool $failed = false;
    private readonly TellEventNormalizer $events;

    public function __construct(
        private readonly TellPaths $paths,
        private readonly TellConfig $config,
        private readonly TellOptions $options,
    ) {
        $this->events = new TellEventNormalizer(
            branch: $options->branch,
            session: $options->session,
        );
    }

    public function attach(AgentLoop $loop): void {
        if (!$this->config->executionTraces) {
            return;
        }
        $loop->wiretap(function (object $event): void {
            $this->record($event);
        });
    }

    private function record(object $event): void {
        if ($this->failed || !$event instanceof Event) {
            return;
        }
        try {
            $this->startTrace($event);
            if ($this->path === null) {
                return;
            }
            $line = json_encode(
                $this->eventPayload($event),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ) . "\n";
            $created = !is_file($this->path);
            if (@file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
                $this->failed = true;

                return;
            }
            if ($created) {
                @chmod($this->path, 0600);
            }
        } catch (Throwable) {
            $this->failed = true;
        }
    }

    private function startTrace(Event $event): void {
        if ($this->path !== null || !$event instanceof AgentExecutionStarted) {
            return;
        }
        if ($this->options->session !== null) {
            $this->path = $this->sessionTracePath($this->options->session);

            return;
        }
        $utc = $event->createdAt->setTimezone(new DateTimeZone('UTC'));
        $directory = (new TellStorage($this->paths))->ensureTraceDate($utc->format('Y-m-d'));
        $executionId = preg_replace('/[^A-Za-z0-9._-]/', '_', $event->executionId) ?? 'unknown';
        $this->path = $directory . DIRECTORY_SEPARATOR . $executionId . '.jsonl';
    }

    private function sessionTracePath(string $session): string {
        $directory = (new TellStorage($this->paths))->ensureSessionTraces();
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $session) ?? 'session';
        $slug = substr($safe, 0, 80);
        $hash = substr(hash('sha256', $session), 0, 12);

        return $directory . DIRECTORY_SEPARATOR . $slug . '-' . $hash . '.jsonl';
    }

    private function events(): TellEventNormalizer {
        return $this->events;
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
