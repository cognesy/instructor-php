<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Qwen;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ConfiguredReasoningTranslator;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningBodyFormat;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningWireFormat;
use InvalidArgumentException;

final class QwenBatchItemEncoder implements CanEncodeBatchItem
{
    private readonly CanMapRequestBody $body;
    private ?string $model = null;
    private bool $hasThinkingMode = false;
    private ?bool $thinkingMode = null;

    public function __construct(private readonly BatchConfig $config)
    {
        $this->body = new ReasoningBodyFormat(
            new QwenBodyFormat($config->inference(), new OpenAIMessageFormat()),
            new ConfiguredReasoningTranslator(ReasoningWireFormat::Qwen),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        if (strlen($item->key()) > 256) {
            throw new InvalidArgumentException('Qwen batch custom_id exceeds 256 bytes.');
        }
        $body = $this->body->toRequestBody($item->request());
        if (array_key_exists('extra_body', $body)) {
            throw new InvalidArgumentException('Qwen batch parameters must be top-level request-body fields, not extra_body.');
        }
        $model = $body['model'] ?? null;
        if (!is_string($model) || $model === '') {
            throw new InvalidArgumentException('Qwen batch item has no model.');
        }
        if ($this->model !== null && $this->model !== $model) {
            throw new InvalidArgumentException('Qwen batch input must use one model.');
        }
        $this->assertRegionModel($model);
        $this->model = $model;

        $thinking = $body['enable_thinking'] ?? null;
        if ($thinking !== null && !is_bool($thinking)) {
            throw new InvalidArgumentException('Qwen batch enable_thinking must be boolean.');
        }
        if ($this->hasThinkingMode && $this->thinkingMode !== $thinking) {
            throw new InvalidArgumentException('Qwen batch input must use one thinking mode.');
        }
        $this->thinkingMode = $thinking;
        $this->hasThinkingMode = true;

        return [
            'custom_id' => $item->key(),
            'method' => 'POST',
            'url' => $this->config->route(),
            'body' => $body,
        ];
    }

    private function assertRegionModel(string $model): void
    {
        if ($this->config->apiBaseUrl() !== 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1') {
            return;
        }
        if (!in_array($model, ['qwen-max', 'qwen-plus', 'qwen-flash', 'qwen-turbo'], true)) {
            throw new InvalidArgumentException("Qwen model {$model} is not documented for Singapore batch inference.");
        }
    }
}
