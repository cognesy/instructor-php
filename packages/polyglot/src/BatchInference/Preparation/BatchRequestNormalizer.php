<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Preparation;

use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\Inference\Core\InferenceRequestPreflight;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use InvalidArgumentException;

final readonly class BatchRequestNormalizer
{
    private InferenceRequestPreflight $preflight;

    public function __construct(
        private string $driverName,
        private string $defaultModel,
        private ?ModelCatalog $models = null,
        bool $allowLossyFallback = false,
    ) {
        $this->preflight = new InferenceRequestPreflight($allowLossyFallback);
    }

    public function normalize(BatchItem $item): BatchItem
    {
        $request = $item->request();
        if ($request->isStreamed() || ($request->options()['stream'] ?? false)) {
            throw new InvalidArgumentException('Streaming inference cannot be submitted to a batch job.');
        }
        if ($request->retryPolicy() !== null || $request->responseCachePolicy() !== ResponseCachePolicy::None) {
            throw new InvalidArgumentException('Local inference retry and response-cache policies are not supported for batch jobs.');
        }

        $model = $request->model() === '' ? $this->defaultModel : $request->model();
        if ($model === '') {
            throw new InvalidArgumentException('Each batch item needs an effective model.');
        }
        $request = $request->withModel($model);
        if ($this->models !== null) {
            $request = $request->withModelProfile($this->models->find($this->driverName, $model));
        }

        return new BatchItem($item->key(), $this->preflight->apply($request));
    }
}
