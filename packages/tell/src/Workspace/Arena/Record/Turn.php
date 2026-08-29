<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;
use Override;
use ValueError;

final readonly class Turn implements StoredRecord
{
    /** @var list<Message> */
    private array $messages;

    /** @var list<ToolCall> */
    private array $toolCalls;

    /** @var list<ToolResult> */
    private array $toolResults;

    /** @param list<Message> $messages @param list<ToolCall> $toolCalls @param list<ToolResult> $toolResults */
    public function __construct(
        private string $id,
        private Lineage $lineage,
        array $messages,
        array $toolCalls = [],
        array $toolResults = [],
        private TurnStatus $status = TurnStatus::Completed,
    ) {
        Input::identifier($id, 'turn id');
        if (!array_is_list($messages) || !array_is_list($toolCalls) || !array_is_list($toolResults)) {
            throw new RecordException('Arena turn collections must be lists.');
        }
        if ($messages === []) {
            throw new RecordException('Arena turns must contain at least one message.');
        }
        $this->messages = $messages;
        $this->toolCalls = $toolCalls;
        $this->toolResults = $toolResults;

        $emptyAssistantMessage = false;
        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                throw new RecordException('Arena turn messages must be record messages.');
            }
            $emptyAssistantMessage = $emptyAssistantMessage || (
                $message->role() === Role::Assistant && $message->parts() === []
            );
        }
        if ($emptyAssistantMessage && $toolCalls === []) {
            throw new RecordException('An empty assistant message requires an Arena tool call.');
        }

        $callsById = [];
        foreach ($toolCalls as $call) {
            if (!$call instanceof ToolCall) {
                throw new RecordException('Arena turn tool calls must be tool calls.');
            }
            if (isset($callsById[$call->id()])) {
                throw new RecordException('Arena turn tool call IDs must be unique.');
            }
            $callsById[$call->id()] = true;
        }

        $resultsByCallId = [];
        foreach ($toolResults as $result) {
            if (!$result instanceof ToolResult) {
                throw new RecordException('Arena turn tool results must be tool results.');
            }
            if (!isset($callsById[$result->callId()])) {
                throw new RecordException('Arena tool result must pair with a tool call in the same turn.');
            }
            if (isset($resultsByCallId[$result->callId()])) {
                throw new RecordException('Arena tool call can have only one result.');
            }
            $resultsByCallId[$result->callId()] = true;
        }
        if (array_keys($callsById) !== array_keys($resultsByCallId)) {
            throw new RecordException('Every Arena tool call must have exactly one result.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['id', 'kind', 'lineage', 'messages', 'schema', 'status', 'toolCalls', 'toolResults']);
        if ($data['kind'] !== 'turn') {
            throw new RecordException('Arena record is not a turn.');
        }
        Input::schema($data['schema']);
        if (!is_string($data['status'])) {
            throw new RecordException('Arena turn status must be supported.');
        }
        try {
            $status = TurnStatus::from($data['status']);
        } catch (ValueError) {
            throw new RecordException('Arena turn status must be supported.');
        }

        $messages = array_map(
            static fn (mixed $message): Message => Message::fromArray(Input::map($message, 'turn message')),
            Input::list($data['messages'], 'turn messages'),
        );
        $toolCalls = array_map(
            static fn (mixed $call): ToolCall => ToolCall::fromArray(Input::map($call, 'tool call')),
            Input::list($data['toolCalls'], 'tool calls'),
        );
        $toolResults = array_map(
            static fn (mixed $result): ToolResult => ToolResult::fromArray(Input::map($result, 'tool result')),
            Input::list($data['toolResults'], 'tool results'),
        );

        return new self(
            Input::identifier($data['id'], 'turn id'),
            Lineage::fromArray(Input::map($data['lineage'], 'turn lineage')),
            $messages,
            $toolCalls,
            $toolResults,
            $status,
        );
    }

    #[Override]
    public function kind(): string {
        return 'turn';
    }

    public function id(): string {
        return $this->id;
    }

    public function lineage(): Lineage {
        return $this->lineage;
    }

    /** @return list<Message> */
    public function messages(): array {
        return $this->messages;
    }

    /** @return list<ToolCall> */
    public function toolCalls(): array {
        return $this->toolCalls;
    }

    /** @return list<ToolResult> */
    public function toolResults(): array {
        return $this->toolResults;
    }

    public function status(): TurnStatus {
        return $this->status;
    }

    #[Override]
    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'kind' => $this->kind(),
            'lineage' => $this->lineage->toArray(),
            'messages' => array_map(static fn (Message $message): array => $message->toArray(), $this->messages),
            'schema' => StoredRecord::SCHEMA_VERSION,
            'status' => $this->status->value,
            'toolCalls' => array_map(static fn (ToolCall $call): array => $call->toArray(), $this->toolCalls),
            'toolResults' => array_map(static fn (ToolResult $result): array => $result->toArray(), $this->toolResults),
        ];
    }
}
