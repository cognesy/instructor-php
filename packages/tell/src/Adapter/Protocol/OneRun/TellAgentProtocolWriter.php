<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Protocol\OneRun;

use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanWriteTellProtocolFrames;
use Cognesy\Tell\Data\TellDiagnostic;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;
use JsonException;
use LogicException;
use Symfony\Component\Console\Output\OutputInterface;

final class TellAgentProtocolWriter implements CanWriteTellProtocolFrames
{
    public const string SCHEMA = 'tell.agent.frame.v2';
    public const int MAX_FRAME_BYTES = 1_048_576;
    private const int MAX_ANSWER_BYTES = 200_000;

    private int $sequence = 0;
    private bool $terminal = false;

    public function __construct(
        private readonly OutputInterface $output,
        private ?string $id = null,
    ) {}

    #[\Override]
    public function identify(string $id): void {
        $this->id = $id;
    }

    #[\Override]
    public function progress(TellProgress $progress): void {
        $this->write('progress', [
            'progress' => [
                'step' => $progress->stepCount(),
                'status' => $progress->status()?->value,
                'hasToolCalls' => $progress->hasToolCalls(),
                'usage' => $progress->usage()->toTokenCounts(),
            ],
        ]);
    }

    #[\Override]
    public function success(TellResult $result): void {
        $answer = self::boundedAnswer($result->text());
        $termination = $result->termination();
        $this->write('result', [
            'result' => [
                'outcome' => 'completed',
                'executionId' => $termination->executionId,
                'status' => $termination->status->value,
                'answer' => $answer,
                'answerTruncated' => strlen($answer) < strlen($result->text()),
                'answerBytes' => strlen($answer),
                'steps' => $termination->stepCount,
                'usage' => $termination->usage->toTokenCounts(),
                'requestedMode' => $result->requestedMode()->value,
                'mode' => $result->mode()->value,
                'publication' => $result->publication()->status->value,
                'trace' => [
                    'executionId' => $result->trace()->executionId,
                    'status' => $result->trace()->status->value,
                ],
                'session' => $result->session(),
                'branch' => $result->branch(),
                'errors' => $termination->errors->toArray(),
                'diagnostics' => $this->externalDiagnostics($result),
            ],
        ], terminal: true);
    }

    #[\Override]
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
            $termination = $result->termination();
            $payload['executionId'] = $termination->executionId;
            $payload['status'] = $termination->status->value;
            $payload['steps'] = $termination->stepCount;
            $payload['usage'] = $termination->usage->toTokenCounts();
            $payload['publication'] = $result->publication()->status->value;
            $payload['trace'] = [
                'executionId' => $result->trace()->executionId,
                'status' => $result->trace()->status->value,
            ];
            $payload['errors'] = $termination->errors->toArray();
            $payload['diagnostics'] = $this->externalDiagnostics($result);
        }
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }
        $this->write('error', ['error' => $payload], terminal: true);
    }

    #[\Override]
    public function cancelled(TellResult $result): void {
        $termination = $result->termination();
        $this->write('cancelled', [
            'cancellation' => [
                'code' => 'cancelled',
                'message' => 'The run was cancelled.',
                'executionId' => $termination->executionId,
                'status' => $termination->status->value,
                'reason' => $termination->stopSignal?->reason->value,
                'steps' => $termination->stepCount,
                'usage' => $termination->usage->toTokenCounts(),
                'publication' => $result->publication()->status->value,
                'trace' => [
                    'executionId' => $result->trace()->executionId,
                    'status' => $result->trace()->status->value,
                ],
                'diagnostics' => $this->externalDiagnostics($result),
            ],
        ], terminal: true);
    }

    #[\Override]
    public function infrastructureFailure(
        string $code,
        string $message,
        TellTermination $termination,
        TellPublication $publication,
        ?TellTraceReference $trace = null,
    ): void {
        $this->write('error', [
            'error' => [
                'code' => $code,
                'message' => $message,
                'executionId' => $termination->executionId,
                'status' => $termination->status->value,
                'reason' => $termination->stopSignal?->reason->value,
                'publication' => $publication->status->value,
                'trace' => match ($trace) {
                    null => ['status' => 'unknown', 'executionId' => $termination->executionId],
                    default => ['status' => $trace->status->value, 'executionId' => $trace->executionId],
                },
            ],
        ], terminal: true);
    }

    #[\Override]
    public function hasTerminalFrame(): bool {
        return $this->terminal;
    }

    /** @param array<string, mixed> $payload */
    private function write(string $type, array $payload, bool $terminal = false): void {
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

    private static function boundedAnswer(string $answer): string {
        if (strlen($answer) <= self::MAX_ANSWER_BYTES) {
            return $answer;
        }

        return mb_strcut($answer, 0, self::MAX_ANSWER_BYTES, 'UTF-8');
    }

    /** @return list<array{code: string, source: string, severity: string, message: string}> */
    private function externalDiagnostics(TellResult $result): array {
        return array_map(
            static fn (TellDiagnostic $diagnostic): array => $diagnostic->toExternalArray(),
            $result->diagnostics(),
        );
    }
}
