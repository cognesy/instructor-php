<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Jina;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use InvalidArgumentException;

class JinaBodyFormat implements CanMapRequestBody
{
    public function __construct(
        private readonly EmbeddingsConfig $config
    ) {}

    public function toRequestBody(EmbeddingsRequest $request): array {
        $inputs = $request->inputs();
        $options = $request->options();
        $model = $request->model() ?: $this->config->model;

        if (count($inputs) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }

        $body = array_filter(array_merge([
            'model' => $model,
            'normalized' => true,
            'embedding_type' => 'float',
            'input' => $inputs,
        ], $options));
        if ($model === 'jina-colbert-v2') {
            $body['input_type'] = $options['input_type'] ?? 'document';
            $body['dimensions'] = $options['dimensions'] ?? 128;
        }
        return $body;
    }
}
