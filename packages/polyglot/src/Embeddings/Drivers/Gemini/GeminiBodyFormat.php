<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Gemini;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use InvalidArgumentException;

class GeminiBodyFormat implements CanMapRequestBody
{
    public function __construct(
        private readonly EmbeddingsConfig $config
    ) {}

    #[\Override]
    public function toRequestBody(EmbeddingsRequest $request): array {
        $inputs = $request->inputs();
        $model = $request->model() ?: $this->config->model;
        $options = $request->options();

        if (count($inputs) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }

        return array_merge([
            'requests' => array_map(
                fn($item) => [
                    'model' => $model,
                    'content' => ['parts' => [['text' => $item]]]
                ],
                $inputs
            ),
        ], $options);
    }
}