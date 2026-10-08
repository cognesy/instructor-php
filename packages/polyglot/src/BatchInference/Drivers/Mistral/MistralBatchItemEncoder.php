<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Mistral;

use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\Mistral\MistralBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use InvalidArgumentException;

final class MistralBatchItemEncoder implements CanEncodeBatchItem
{
    private readonly ReasoningBodyFormat $body;
    private ?string $model = null;

    public function __construct(LLMConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new MistralBodyFormat($config, new OpenAIMessageFormat()),
            new ConfiguredReasoningTranslator(ReasoningWireFormat::NamedEffort),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        $model = $item->request()->model();
        if ($this->model !== null && $this->model !== $model) {
            throw new InvalidArgumentException('Mistral batch requests must use one model.');
        }
        $this->model = $model;
        $body = $this->body->toRequestBody($item->request());
        unset($body['model']);

        return ['custom_id' => $item->key(), 'body' => $body];
    }

    public function model(): string
    {
        return $this->model ?? throw new InvalidArgumentException('Mistral batch has no model.');
    }
}
