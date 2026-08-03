<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Events\Event;
use Cognesy\Tell\Runtime\TellConfig;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\Runtime\TellPaths;
use Cognesy\Tell\Runtime\TellStorage;
use DateTimeZone;
use Throwable;

final class ExecutionTraceWriter
{
    private const string SCHEMA = 'tell.execution-event.v1';

    private ?string $path = null;

    private bool $failed = false;

    public function __construct(
        private readonly TellPaths $paths,
        private readonly TellConfig $config,
        private readonly TellOptions $options,
    ) {}

    public function attach(AgentLoop $loop): void
    {
        if (! $this->config->executionTraces) {
            return;
        }
        $loop->wiretap(function (object $event): void {
            $this->record($event);
        });
    }

    private function record(object $event): void
    {
        if ($this->failed || ! $event instanceof Event) {
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
            )."\n";
            $created = ! is_file($this->path);
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

    private function startTrace(Event $event): void
    {
        if ($this->path !== null || ! $event instanceof AgentExecutionStarted) {
            return;
        }
        if ($this->options->session !== null) {
            $this->path = $this->sessionTracePath($this->options->session);

            return;
        }
        $utc = $event->createdAt->setTimezone(new DateTimeZone('UTC'));
        $directory = (new TellStorage($this->paths))->ensureTraceDate($utc->format('Y-m-d'));
        $executionId = preg_replace('/[^A-Za-z0-9._-]/', '_', $event->executionId) ?? 'unknown';
        $this->path = $directory.DIRECTORY_SEPARATOR.$executionId.'.jsonl';
    }

    private function sessionTracePath(string $session): string
    {
        $directory = (new TellStorage($this->paths))->ensureSessionTraces();
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $session) ?? 'session';
        $slug = substr($safe, 0, 80);
        $hash = substr(hash('sha256', $session), 0, 12);

        return $directory.DIRECTORY_SEPARATOR.$slug.'-'.$hash.'.jsonl';
    }

    /** @return array<string, mixed> */
    private function eventPayload(Event $event): array
    {
        $data = TracePayload::sanitize(
            $event->data,
            $this->config->includePayloads,
            $this->config->maxStringLength,
        );

        return [
            'schema' => self::SCHEMA,
            'timestamp' => $event->createdAt->format(DATE_ATOM),
            'event' => $event->name(),
            'eventId' => $event->id,
            'level' => $event->logLevel,
            'agent' => $this->options->agent,
            'session' => $this->options->session,
            'workspace' => $this->workspace(),
            'data' => $data,
        ];
    }

    private function workspace(): string
    {
        $resolved = realpath($this->options->directory);

        return match ($resolved) {
            false => $this->options->directory,
            default => $resolved,
        };
    }
}
