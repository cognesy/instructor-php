<?php declare(strict_types=1);

namespace Cognesy\Agents\Context;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Utils\Metadata;

final readonly class AgentContext
{
    private MessageStore $store;
    private Metadata $metadata;
    private string $systemPrompt;
    private ResponseFormat $responseFormat;

    public function __construct(
        ?MessageStore $store = null,
        Metadata|array|null $metadata = null,
        string $systemPrompt = '',
        ResponseFormat|array|null $responseFormat = null,
    ) {
        $this->store = $store ?? new MessageStore();
        $this->metadata = match (true) {
            $metadata === null => new Metadata(),
            $metadata instanceof Metadata => $metadata,
            is_array($metadata) => new Metadata($metadata),
            default => new Metadata(),
        };
        $this->systemPrompt = $systemPrompt;
        $this->responseFormat = match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
            default => new ResponseFormat(),
        };
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function store(): MessageStore {
        return $this->store;
    }

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function systemPrompt(): string {
        return $this->systemPrompt;
    }

    public function responseFormat(): ResponseFormat {
        return $this->responseFormat;
    }

    public function messages(): Messages {
        return $this->store->section(ContextSections::DEFAULT)->get()->messages();
    }

    public function toCachedContext(array $toolSchemas = []): CachedInferenceContext {
        $messages = match(true) {
            $this->systemPrompt === '' => [],
            default => [['role' => 'system', 'content' => $this->systemPrompt]],
        };
        $responseFormat = match(true) {
            $this->responseFormat->isEmpty() => [],
            default => $this->responseFormat,
        };
        return new CachedInferenceContext(
            messages: $messages,
            tools: $toolSchemas,
            responseFormat: $responseFormat,
        );
    }

    // MUTATORS /////////////////////////////////////////////////

    public function with(
        ?MessageStore $store = null,
        ?Metadata $metadata = null,
        ?string $systemPrompt = null,
        ?ResponseFormat $responseFormat = null,
    ): self {
        return new self(
            store: $store ?? $this->store,
            metadata: $metadata ?? $this->metadata,
            systemPrompt: $systemPrompt ?? $this->systemPrompt,
            responseFormat: $responseFormat ?? $this->responseFormat,
        );
    }

    public function withMessageStore(MessageStore $store): self {
        return $this->with(store: $store);
    }

    public function withMessages(Messages $messages): self {
        return $this->with(
            store: $this->store->section(ContextSections::DEFAULT)->setMessages($messages),
        );
    }

    public function withAppendedMessages(Messages $messages): self {
        $store = $this->store->section(ContextSections::DEFAULT)->appendMessages($messages);
        return $this->with(store: $store);
    }

    public function withMetadataKey(string $name, mixed $value): self {
        return $this->with(metadata: $this->metadata->withKeyValue($name, $value));
    }

    public function withMetadata(Metadata $metadata): self {
        return $this->with(metadata: $metadata);
    }

    public function withSystemPrompt(string $systemPrompt): self {
        return $this->with(systemPrompt: $systemPrompt);
    }

    public function withResponseFormat(ResponseFormat|array $responseFormat): self {
        $format = match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
        };
        return $this->with(responseFormat: $format);
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array {
        return [
            'metadata' => $this->metadata->toArray(),
            'systemPrompt' => $this->systemPrompt,
            'responseFormat' => $this->responseFormat->toArray(),
            'messageStore' => $this->store->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            store: MessageStore::fromArray($data['messageStore'] ?? []),
            metadata: Metadata::fromArray($data['metadata'] ?? []),
            systemPrompt: $data['systemPrompt'] ?? '',
            responseFormat: ResponseFormat::fromArray($data['responseFormat'] ?? []),
        );
    }

}
