<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Groq;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Drivers\Groq\GroqBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;

final class GroqBatchItemEncoder implements CanEncodeBatchItem
{
    private readonly CanMapRequestBody $body;

    public function __construct(private readonly BatchConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new GroqBodyFormat($config->inference(), new OpenAIMessageFormat()),
            new UnsupportedReasoningTranslator(),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        return [
            'custom_id' => $item->key(),
            'method' => 'POST',
            'url' => $this->config->route(),
            'body' => $this->body->toRequestBody($item->request()),
        ];
    }
}
