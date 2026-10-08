<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Together;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;
use InvalidArgumentException;

final readonly class TogetherBatchItemEncoder implements CanEncodeBatchItem
{
    private CanMapRequestBody $body;

    public function __construct(BatchConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new OpenAICompatibleBodyFormat($config->inference(), new OpenAIMessageFormat()),
            new UnsupportedReasoningTranslator(),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        if (strlen($item->key()) > 64) {
            throw new InvalidArgumentException('Together batch custom_id exceeds 64 bytes.');
        }

        return [
            'custom_id' => $item->key(),
            'body' => $this->body->toRequestBody($item->request()),
        ];
    }
}
