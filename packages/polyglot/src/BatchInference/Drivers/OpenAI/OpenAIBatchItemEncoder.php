<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\OpenAI;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use InvalidArgumentException;

final class OpenAIBatchItemEncoder implements CanEncodeBatchItem
{
    private readonly CanMapRequestBody $body;
    private ?string $model = null;

    public function __construct(private readonly BatchConfig $config)
    {
        [$format, $reasoning] = match ($config->codec()) {
            'openai-chat' => [
                new OpenAIBodyFormat($config->inference(), new OpenAIMessageFormat()),
                ReasoningWireFormat::NamedEffort,
            ],
            'openai-responses' => [
                new OpenResponsesBodyFormat($config->inference(), new OpenResponsesMessageFormat()),
                ReasoningWireFormat::OpenResponses,
            ],
            default => throw new InvalidArgumentException('Unsupported OpenAI batch request codec.'),
        };
        $this->body = new ReasoningBodyFormat($format, new ConfiguredReasoningTranslator($reasoning));
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        $model = $item->request()->model();
        if ($this->model !== null && $this->model !== $model) {
            throw new InvalidArgumentException('OpenAI batch input must use one model.');
        }
        $this->model = $model;

        return [
            'custom_id' => $item->key(),
            'method' => 'POST',
            'url' => $this->config->route(),
            'body' => $this->body->toRequestBody($item->request()),
        ];
    }
}
