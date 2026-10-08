<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use InvalidArgumentException;

final readonly class BatchItem
{
    public function __construct(private string $key, private InferenceRequest $request)
    {
        if ($key === '' || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Batch item key must be nonempty and contain no control characters.');
        }
    }

    public static function of(string $key, InferenceRequest $request): self
    {
        return new self($key, $request);
    }

    public function key(): string
    {
        return $this->key;
    }
    public function request(): InferenceRequest
    {
        return $this->request;
    }
}
