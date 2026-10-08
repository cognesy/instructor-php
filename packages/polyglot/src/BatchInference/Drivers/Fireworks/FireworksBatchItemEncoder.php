<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Fireworks;

use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\Fireworks\FireworksBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\UnsupportedReasoningTranslator;
use InvalidArgumentException;
use Override;

final readonly class FireworksBatchItemEncoder implements CanEncodeBatchItem
{
    private ReasoningBodyFormat $body;

    public function __construct(private LLMConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new FireworksBodyFormat($config, new OpenAIMessageFormat()),
            new UnsupportedReasoningTranslator(),
        );
    }

    #[Override]
    public function encode(BatchItem $item): array
    {
        $body = $this->body->toRequestBody($item->request());
        if (($body['model'] ?? null) !== $this->config->model) {
            throw new InvalidArgumentException('Fireworks batch uses one job-level model.');
        }
        if (($body['stream'] ?? false) || array_key_exists('stream_options', $body)) {
            throw new InvalidArgumentException('Fireworks batch items cannot request streamed responses.');
        }
        unset($body['model']);
        if (array_key_exists('max_completion_tokens', $body)) {
            $body['max_tokens'] ??= $body['max_completion_tokens'];
            unset($body['max_completion_tokens']);
        }

        return ['custom_id' => $item->key(), 'body' => $body];
    }
}
