<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\Cohere;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use InvalidArgumentException;

class CohereBodyFormat implements CanMapRequestBody
{
    public function __construct(
        private readonly EmbeddingsConfig $config
    ) {}

    public function toRequestBody(EmbeddingsRequest $request): array {
        $inputs = $request->inputs();
        $model = $request->model() ?: $this->config->model;
        $options = $request->options();
        $options['input_type'] = $options['input_type'] ?? 'search_document';

        if (count($inputs) > $this->config->maxInputs) {
            throw new InvalidArgumentException("Number of inputs exceeds the limit of {$this->config->maxInputs}");
        }

        return array_filter(array_merge([
            'texts' => $inputs,
            'model' => $model,
            'embedding_types' => ['float'],
            'truncate' => 'END',
        ], $options));
    }
}