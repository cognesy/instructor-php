<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Tell\Observability\TellEventNormalizer;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The machine-shaped progress channel: one bracketed key=value line per event.
 *
 * Kinds and metadata keys are the ones from the normalized `tell.event.v1`
 * contract, so a line can be read against the same vocabulary as the event
 * stream. Arguments and results are read from the raw event and added locally;
 * they are never put into the envelope, which stays payload-free.
 */
final readonly class EventProgress
{
    private const int MAX_VALUE = 512;

    public function __construct(
        private OutputInterface $stderr,
        private bool $detailed = false,
        private bool $quiet = false,
        private bool $heartbeat = true,
    ) {}

    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void
    {
        if ($this->quiet || (! $this->detailed && ! $this->heartbeat)) {
            return;
        }
        $normalizer = $events ?? new TellEventNormalizer;
        $loop->wiretap(function (object $event) use ($normalizer): void {
            $envelope = $normalizer->normalize($event);
            $line = $this->line($envelope['kind'], $envelope['metadata'], $event);
            if ($line !== null) {
                // OUTPUT_RAW: arguments and results routinely contain angle
                // brackets that the console formatter would read as markup.
                $this->stderr->writeln($line, OutputInterface::OUTPUT_RAW);
            }
        });
    }

    /** @param array<string, int|float|string|bool|null> $metadata */
    private function line(string $kind, array $metadata, object $event): ?string
    {
        return match ($kind) {
            'inference.started' => $this->emit('inference.start', [
                'step' => $metadata['step'] ?? 0,
                'messages' => $this->detail($metadata, 'messages'),
                'model' => $this->detail($metadata, 'model'),
            ]),
            'inference.completed' => $this->detailed ? $this->emit('inference.complete', [
                'step' => $metadata['step'] ?? 0,
                'in' => $metadata['inputTokens'] ?? null,
                'out' => $metadata['outputTokens'] ?? null,
                'finish' => $metadata['finishReason'] ?? null,
            ]) : null,
            'step.started' => $this->detailed ? $this->emit('step.start', [
                'step' => $metadata['step'] ?? 0,
                'messages' => $metadata['messages'] ?? null,
                'tools' => $metadata['tools'] ?? null,
            ]) : null,
            'step.completed' => $this->detailed ? $this->emit('step.complete', [
                'step' => $metadata['step'] ?? 0,
                'toolCalls' => $this->flag($metadata['hasToolCalls'] ?? null),
                'errors' => $metadata['errors'] ?? null,
                'in' => $metadata['inputTokens'] ?? null,
                'out' => $metadata['outputTokens'] ?? null,
                'finish' => $metadata['finishReason'] ?? null,
            ]) : null,
            'tool.started' => $this->detailed ? $this->emit('tool.start', [
                'name' => $metadata['tool'] ?? 'unknown',
                'step' => $metadata['step'] ?? 0,
                ...$this->payload('args', $event instanceof ToolCallStarted ? $event->args : null),
            ]) : null,
            'tool.completed' => $this->detailed ? $this->emit('tool.complete', [
                'name' => $metadata['tool'] ?? 'unknown',
                'status' => $this->status($metadata, $event),
                'step' => $metadata['step'] ?? 0,
                'duration' => ($metadata['durationMs'] ?? 0).'ms',
                'error' => $event instanceof ToolCallCompleted ? $event->error : null,
                ...$this->payload('result', $event instanceof ToolCallCompleted ? $event->result : null),
            ]) : null,
            'tool.blocked' => $this->detailed ? $this->emit('tool.blocked', [
                'name' => $metadata['tool'] ?? 'unknown',
                'step' => $metadata['step'] ?? 0,
            ]) : null,
            'stop.requested' => $this->detailed ? $this->emit('stop', [
                'reason' => $metadata['reason'] ?? null,
            ]) : null,
            'execution.completed' => $this->detailed ? $this->emit('execution.complete', [
                'status' => $metadata['status'] ?? null,
                'steps' => $metadata['steps'] ?? null,
                'in' => $metadata['inputTokens'] ?? null,
                'out' => $metadata['outputTokens'] ?? null,
            ]) : null,
            default => null,
        };
    }

    /**
     * A tool that returns its own failure envelope still reports a successful
     * call, so the status has to agree with the result rather than only with
     * whether the invocation threw.
     *
     * @param  array<string, int|float|string|bool|null>  $metadata
     */
    private function status(array $metadata, object $event): string
    {
        $failed = ($metadata['success'] ?? false) !== true
            || ($event instanceof ToolCallCompleted && ToolResultText::error($event->result) !== null);

        return $failed ? 'failed' : 'ok';
    }

    /**
     * A payload is one bounded single-line value, and it is always valid JSON:
     * an excerpt is emitted as a JSON string rather than as a truncated object
     * a reader could not parse. The companion `<key>Bytes` appears only on an
     * excerpt, so its presence is what says the value is one.
     *
     * @return array<string, int|string|null>
     */
    private function payload(string $key, mixed $value): array
    {
        $encoded = $this->encode($value);
        if ($encoded === null) {
            return [];
        }
        $length = strlen($encoded);
        if ($length <= self::MAX_VALUE) {
            return [$key => $encoded];
        }

        return [
            $key => (string) $this->encode(mb_substr($encoded, 0, self::MAX_VALUE)),
            $key.'Bytes' => $length,
        ];
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '"<unencodable>"';
        }
    }

    /** @param array<string, int|float|string|bool|null> $metadata */
    private function detail(array $metadata, string $key): int|float|string|bool|null
    {
        return $this->detailed ? ($metadata[$key] ?? null) : null;
    }

    private function flag(mixed $value): ?string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            default => null,
        };
    }

    /** @param array<string, int|float|string|bool|null> $pairs */
    private function emit(string $kind, array $pairs): string
    {
        $line = '['.$kind.']';
        foreach ($pairs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $line .= ' '.$key.'='.(is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
        }

        return $line;
    }
}
