<?php

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Core\MessageCompilation\CanCompileMessages;
use Cognesy\Agents\Core\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Utils\Metadata;

class AgentContext
{
    public const DEFAULT_SECTION = 'messages';
    public const BUFFER_SECTION = 'buffer';
    public const SUMMARY_SECTION = 'summary';
    public const EXECUTION_BUFFER_SECTION = 'execution_buffer';

    private MessageStore $store;
    private Metadata $metadata;
    private CachedInferenceContext $cache;

    public function __construct(
        ?MessageStore $store = null,
        Metadata|array|null $variables = null,
        ?CachedInferenceContext $cache = null,
    ) {
        $this->store = $store ?? new MessageStore();
        $this->metadata = match (true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedInferenceContext();
    }

    public function messages() : Messages {
        return $this->store->section(self::DEFAULT_SECTION)->get()->messages();
    }

    public function messageProvider(): CanCompileMessages {
        return (new SelectedSections([
            self::SUMMARY_SECTION,
            self::BUFFER_SECTION,
            self::DEFAULT_SECTION,
            self::EXECUTION_BUFFER_SECTION,
        ]));
    }

    public function messageStore(): MessageStore {
        return $this->store;
    }

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function cache(): CachedInferenceContext {
        return $this->cache;
    }


    // MUTATORS /////////////////////////////////////////////////

    public function with(
        ?MessageStore $store = null,
        ?Metadata $metadata = null,
        ?CachedInferenceContext $cache = null,
    ): self {
        return new self(
            store: $store ?? $this->store,
            variables: $metadata ?? $this->metadata,
            cache: $cache ?? $this->cache,
        );
    }

    public function withMessages(Messages $messages): self {
        return $this->with(store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages));
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array {
        return [
            'store' => $this->store->toArray(),
            'metadata' => $this->metadata->toArray(),
            'cache' => $this->cache->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            store: isset($data['store']) ? MessageStore::fromArray($data['store']) : null,
            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : null,
            cache: isset($data['cache']) ? CachedInferenceContext::fromArray($data['cache']) : null,
        );
    }
}