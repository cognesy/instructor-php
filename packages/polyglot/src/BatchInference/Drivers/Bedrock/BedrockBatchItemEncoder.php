<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Bedrock;

use Cognesy\Polyglot\BatchInference\Config\BedrockBatchSettings;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use InvalidArgumentException;

final readonly class BedrockBatchItemEncoder implements CanEncodeBatchItem
{
    private AnthropicBodyFormat $body;

    public function __construct()
    {
        $this->body = new AnthropicBodyFormat(
            new LLMConfig(driver: 'anthropic', model: BedrockBatchSettings::MODEL_ID),
            new AnthropicMessageFormat(),
        );
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        $request = $item->request();
        if ($request->model() !== BedrockBatchSettings::MODEL_ID) {
            throw new InvalidArgumentException('Bedrock batch currently admits only Claude 3 Haiku InvokeModel input.');
        }
        if ($request->hasTools() || $request->hasToolChoice() || $request->hasResponseFormat() || !$request->reasoning()->isDefault()) {
            throw new InvalidArgumentException('Bedrock batch does not admit tools, structured output, or reasoning options.');
        }
        if ($item->key() === '' || strlen($item->key()) > 256 || str_contains($item->key(), "\n")) {
            throw new InvalidArgumentException('Bedrock recordId must be a nonempty single-line key up to 256 bytes.');
        }
        $body = $this->body->toRequestBody($request);
        unset($body['model']);
        $body['anthropic_version'] = 'bedrock-2023-05-31';
        return ['recordId' => $item->key(), 'modelInput' => $body];
    }
}
