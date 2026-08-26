<?php

declare(strict_types=1);

namespace Cognesy\Tell\Protocol;

use Cognesy\Tell\Contracts\CanWriteTellProtocolFrames;
use Cognesy\Tell\TellProgress;
use Cognesy\Tell\TellResult;
use JsonException;
use LogicException;
use Symfony\Component\Console\Output\OutputInterface;

final class TellAgentProtocolWriter implements CanWriteTellProtocolFrames
{
    public const string SCHEMA = 'tell.agent.frame.v1';

    public const int MAX_FRAME_BYTES = 1_048_576;

    private const int MAX_ANSWER_BYTES = 200_000;

    private int $sequence = 0;

    private bool $terminal = false;

    public function __construct(
        private readonly OutputInterface $output,
        private ?string $id = null,
    ) {}

    public function identify(string $id): void
    {
        $this->id = $id;
    }

    public function progress(TellProgress $progress): void
    {
        $this->write('progress', [
            'progress' => [
                'step' => $progress->stepCount(),
                'status' => $progress->status()?->value,
                'hasToolCalls' => $progress->hasToolCalls(),
                'usage' => $progress->usage()->toTokenCounts(),
            ],
        ]);
    }

    public function success(TellResult $result): void
    {
        $answer = self::boundedAnswer($result->text());
        $this->write('result', [
            'result' => [
                'outcome' => 'completed',
                'status' => $result->status()?->value,
                'answer' => $answer,
                'answerTruncated' => strlen($answer) < strlen($result->text()),
                'answerBytes' => strlen($answer),
                'steps' => $result->state()->steps()->count(),
                'usage' => $result->usage()->toTokenCounts(),
                'durable' => $result->isDurable(),
                'transient' => $result->isTransient(),
                'session' => $result->session(),
                'branch' => $result->branch(),
                'diagnostics' => $this->externalDiagnostics($result),
            ],
        ], terminal: true);
    }

    public function error(
        string $code,
        string $message,
        ?TellResult $result = null,
        ?string $reason = null,
    ): void {
        $payload = [
            'code' => $code,
            'message' => $message,
        ];
        if ($result !== null) {
            $payload['status'] = $result->status()?->value;
            $payload['steps'] = $result->state()->steps()->count();
            $payload['usage'] = $result->usage()->toTokenCounts();
            $payload['diagnostics'] = $this->externalDiagnostics($result);
        }
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }
        $this->write('error', ['error' => $payload], terminal: true);
    }

    public function cancelled(TellResult $result): void
    {
        $this->write('cancelled', [
            'cancellation' => [
                'code' => 'cancelled',
                'message' => 'The run was cancelled.',
                'status' => $result->status()?->value,
                'steps' => $result->state()->steps()->count(),
                'usage' => $result->usage()->toTokenCounts(),
                'diagnostics' => $this->externalDiagnostics($result),
            ],
        ], terminal: true);
    }

    public function hasTerminalFrame(): bool
    {
        return $this->terminal;
    }

    /** @param array<string, mixed> $payload */
    private function write(string $type, array $payload, bool $terminal = false): void
    {
        if ($this->terminal) {
            throw new LogicException('Tell agent protocol already emitted a terminal frame.');
        }

        $frame = [
            'schema' => self::SCHEMA,
            'id' => $this->id,
            'sequence' => ++$this->sequence,
            'type' => $type,
            ...$payload,
        ];
        try {
            $json = json_encode($frame, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException) {
            throw new LogicException('Tell agent protocol could not encode a response frame.');
        }
        if (strlen($json) > self::MAX_FRAME_BYTES) {
            throw new LogicException('Tell agent protocol response frame exceeds its size limit.');
        }

        $this->output->writeln($json, OutputInterface::OUTPUT_RAW);
        $this->terminal = $terminal;
    }

    private static function boundedAnswer(string $answer): string
    {
        if (strlen($answer) <= self::MAX_ANSWER_BYTES) {
            return $answer;
        }

        return mb_strcut($answer, 0, self::MAX_ANSWER_BYTES, 'UTF-8');
    }

    /** @return list<array{code: string, source: string, severity: string, message: string}> */
    private function externalDiagnostics(TellResult $result): array
    {
        return array_map(
            static fn (\Cognesy\Tell\Diagnostics\TellDiagnostic $diagnostic): array => $diagnostic->toExternalArray(),
            $result->diagnostics(),
        );
    }
}
