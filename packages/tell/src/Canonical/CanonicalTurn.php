<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalTurn implements CanonicalRecord
{
    /** @var list<CanonicalMessage> */
    private array $messages;

    /** @var list<CanonicalToolCall> */
    private array $toolCalls;

    /** @var list<CanonicalToolResult> */
    private array $toolResults;

    /** @param list<CanonicalMessage> $messages @param list<CanonicalToolCall> $toolCalls @param list<CanonicalToolResult> $toolResults */
    public function __construct(
        private string $id,
        private CanonicalLineage $lineage,
        array $messages,
        array $toolCalls = [],
        array $toolResults = [],
        private CanonicalTurnStatus $status = CanonicalTurnStatus::Completed,
    ) {
        CanonicalInput::identifier($id, 'turn id');
        if (! array_is_list($messages) || ! array_is_list($toolCalls) || ! array_is_list($toolResults)) {
            throw new CanonicalValidationException('Canonical turn collections must be lists.');
        }
        if ($messages === []) {
            throw new CanonicalValidationException('Canonical turns must contain at least one message.');
        }
        $this->messages = $messages;
        $this->toolCalls = $toolCalls;
        $this->toolResults = $toolResults;

        $emptyAssistantMessage = false;
        foreach ($messages as $message) {
            if (! $message instanceof CanonicalMessage) {
                throw new CanonicalValidationException('Canonical turn messages must be messages.');
            }
            $emptyAssistantMessage = $emptyAssistantMessage || (
                $message->role() === CanonicalRole::Assistant && $message->parts() === []
            );
        }
        if ($emptyAssistantMessage && $toolCalls === []) {
            throw new CanonicalValidationException('An empty assistant message requires a canonical tool call.');
        }

        $callsById = [];
        foreach ($toolCalls as $call) {
            if (! $call instanceof CanonicalToolCall) {
                throw new CanonicalValidationException('Canonical turn tool calls must be tool calls.');
            }
            if (isset($callsById[$call->id()])) {
                throw new CanonicalValidationException('Canonical turn tool call IDs must be unique.');
            }
            $callsById[$call->id()] = true;
        }

        $resultsByCallId = [];
        foreach ($toolResults as $result) {
            if (! $result instanceof CanonicalToolResult) {
                throw new CanonicalValidationException('Canonical turn tool results must be tool results.');
            }
            if (! isset($callsById[$result->callId()])) {
                throw new CanonicalValidationException('Canonical tool result must pair with a tool call in the same turn.');
            }
            if (isset($resultsByCallId[$result->callId()])) {
                throw new CanonicalValidationException('Canonical tool call can have only one result.');
            }
            $resultsByCallId[$result->callId()] = true;
        }
        if (array_keys($callsById) !== array_keys($resultsByCallId)) {
            throw new CanonicalValidationException('Every canonical tool call must have exactly one result.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['id', 'kind', 'lineage', 'messages', 'schema', 'status', 'toolCalls', 'toolResults']);
        if ($data['kind'] !== 'turn') {
            throw new CanonicalValidationException('Canonical record is not a turn.');
        }
        if (! is_int($data['schema'])) {
            throw new CanonicalValidationException('Canonical schema must be an integer.');
        }
        CanonicalSchema::assertSupported($data['schema']);
        if (! is_string($data['status'])) {
            throw new CanonicalValidationException('Canonical turn status must be supported.');
        }
        try {
            $status = CanonicalTurnStatus::from($data['status']);
        } catch (\ValueError) {
            throw new CanonicalValidationException('Canonical turn status must be supported.');
        }

        $messages = array_map(
            static fn (mixed $message): CanonicalMessage => CanonicalMessage::fromArray(CanonicalInput::map($message, 'turn message')),
            CanonicalInput::list($data['messages'], 'turn messages'),
        );
        $toolCalls = array_map(
            static fn (mixed $call): CanonicalToolCall => CanonicalToolCall::fromArray(CanonicalInput::map($call, 'tool call')),
            CanonicalInput::list($data['toolCalls'], 'tool calls'),
        );
        $toolResults = array_map(
            static fn (mixed $result): CanonicalToolResult => CanonicalToolResult::fromArray(CanonicalInput::map($result, 'tool result')),
            CanonicalInput::list($data['toolResults'], 'tool results'),
        );

        return new self(
            CanonicalInput::identifier($data['id'], 'turn id'),
            CanonicalLineage::fromArray(CanonicalInput::map($data['lineage'], 'turn lineage')),
            $messages,
            $toolCalls,
            $toolResults,
            $status,
        );
    }

    #[\Override]
    public function kind(): string
    {
        return 'turn';
    }

    #[\Override]
    public function schema(): int
    {
        return CanonicalSchema::VERSION;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function lineage(): CanonicalLineage
    {
        return $this->lineage;
    }

    /** @return list<CanonicalMessage> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return list<CanonicalToolCall> */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    /** @return list<CanonicalToolResult> */
    public function toolResults(): array
    {
        return $this->toolResults;
    }

    public function status(): CanonicalTurnStatus
    {
        return $this->status;
    }

    #[\Override]
    /** @return array<string, mixed> */
    public function toCanonicalArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind(),
            'lineage' => $this->lineage->toCanonicalArray(),
            'messages' => array_map(static fn (CanonicalMessage $message): array => $message->toCanonicalArray(), $this->messages),
            'schema' => $this->schema(),
            'status' => $this->status->value,
            'toolCalls' => array_map(static fn (CanonicalToolCall $call): array => $call->toCanonicalArray(), $this->toolCalls),
            'toolResults' => array_map(static fn (CanonicalToolResult $result): array => $result->toCanonicalArray(), $this->toolResults),
        ];
    }
}
