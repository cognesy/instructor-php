<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Anthropic;

use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use InvalidArgumentException;

final readonly class AnthropicBatchItemEncoder implements CanEncodeBatchItem
{
    private ReasoningBodyFormat $body;

    public function __construct(LLMConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new AnthropicBodyFormat($config, new AnthropicMessageFormat()),
            new ConfiguredReasoningTranslator(ReasoningWireFormat::Anthropic),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $item->key()) !== 1) {
            throw new InvalidArgumentException('Anthropic batch custom_id must match [A-Za-z0-9_-]{1,64}.');
        }
        return [
            'custom_id' => $item->key(),
            'params' => $this->body->toRequestBody($item->request()),
        ];
    }
}
