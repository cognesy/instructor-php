<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\XAI;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\XAI\XAiMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;

final readonly class XAIBatchItemEncoder implements CanEncodeBatchItem
{
    private ReasoningBodyFormat $body;

    public function __construct(private BatchConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new OpenAICompatibleBodyFormat($config->inference(), new XAiMessageFormat()),
            new ConfiguredReasoningTranslator(ReasoningWireFormat::NamedEffort),
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
