<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Serialization;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

final readonly class ContinuationAgentStateSerializer implements CanSerializeAgentState
{
    public function __construct(
        private ContinuationSerializationConfig $config = new ContinuationSerializationConfig(),
    ) {}

    #[\Override]
    public function serialize(AgentState $state): array {
        return [
            'version' => 1,
            'agent_id' => $state->agentId,
            'parent_agent_id' => $state->parentAgentId,
            'status' => $state->status()->value,
            'metadata' => $state->metadata()->toArray(),
            'messageStore' => [
                'sections' => $this->serializeSections($state->store()),
                'parameters' => $state->store()->parameters->toArray(),
            ],
        ];
    }

    #[\Override]
    public function deserialize(array $data): AgentState {
        $storeData = $data['messageStore'] ?? $data['message_store'] ?? [];
        $storeData = $this->normalizeStoreData($storeData, $data);

        return AgentState::fromArray([
            'agentId' => $data['agent_id'] ?? null,
            'parentAgentId' => $data['parent_agent_id'] ?? null,
            'status' => AgentStatus::InProgress->value,
            'metadata' => $data['metadata'] ?? [],
            'messageStore' => $storeData,
        ]);
    }

    /**
     * @return list<array{name: string, messages: list<array<array-key, mixed>>}>
     */
    private function serializeSections(MessageStore $store): array {
        return array_map(
            fn(Section $section): array => [
                'name' => $section->name(),
                'messages' => $this->serializeMessages($section->messages()->toArray()),
            ],
            $store->sections()->all(),
        );
    }

    /**
     * @param list<array<array-key, mixed>> $messages
     * @return list<array<array-key, mixed>>
     */
    private function serializeMessages(array $messages): array {
        if ($this->config->maxMessagesPerSection > 0 && count($messages) > $this->config->maxMessagesPerSection) {
            $messages = array_slice($messages, -$this->config->maxMessagesPerSection);
        }

        return array_map(
            fn(array $message): array => $this->serializeMessage($message),
            $messages,
        );
    }

    /**
     * @param array<array-key, mixed> $message
     * @return array<array-key, mixed>
     */
    private function serializeMessage(array $message): array {
        $content = $message['content'] ?? '';
        if (is_string($content) && $this->config->maxContentLength > 0) {
            if (strlen($content) > $this->config->maxContentLength) {
                $content = substr($content, 0, $this->config->maxContentLength) . '...';
            }
        }

        if (!$this->config->includeToolResults && ($message['role'] ?? '') === 'tool') {
            $content = '[tool result omitted]';
        }

        $metadata = $message['_metadata'] ?? $message['metadata'] ?? [];
        if ($this->config->redactToolArgs) {
            $metadata = $this->redactToolArgs($metadata);
        }

        $serialized = [
            'role' => $message['role'] ?? 'user',
            'content' => $content,
        ];

        if (isset($message['name']) && $message['name'] !== '') {
            $serialized['name'] = $message['name'];
        }

        if ($metadata !== []) {
            $serialized['_metadata'] = $metadata;
        }

        return $serialized;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function redactToolArgs(array $metadata): array {
        if (!isset($metadata['tool_calls']) || !is_array($metadata['tool_calls'])) {
            return $metadata;
        }

        $metadata['tool_calls'] = array_map(
            fn(array $toolCall): array => [
                'id' => $toolCall['id'] ?? null,
                'name' => $this->resolveToolName($toolCall),
            ],
            $metadata['tool_calls'],
        );

        return $metadata;
    }

    /**
     * @param array<string, mixed> $toolCall
     */
    private function resolveToolName(array $toolCall): string {
        if (isset($toolCall['name']) && is_string($toolCall['name'])) {
            return $toolCall['name'];
        }

        $function = $toolCall['function'] ?? null;
        if (is_array($function) && isset($function['name']) && is_string($function['name'])) {
            return $function['name'];
        }

        return 'unknown';
    }

    /**
     * @param array<array-key, mixed> $storeData
     * @param array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    private function normalizeStoreData(array $storeData, array $payload): array {
        if ($storeData === [] && isset($payload['messages']) && is_array($payload['messages'])) {
            $storeData = $payload['messages'];
        }

        if (isset($storeData['sections'])) {
            return $storeData;
        }

        if ($storeData === []) {
            return [
                'sections' => [],
            ];
        }

        return [
            'sections' => [
                [
                    'name' => HandlesMessageStore::DEFAULT_SECTION,
                    'messages' => $storeData,
                ],
            ],
        ];
    }
}
