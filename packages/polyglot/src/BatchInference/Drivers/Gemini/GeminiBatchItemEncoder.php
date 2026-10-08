<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Gemini;

use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchManifest;
use Cognesy\Polyglot\BatchInference\Enums\GeminiBatchInputMode;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use InvalidArgumentException;

final class GeminiBatchItemEncoder implements CanEncodeBatchItem
{
    private readonly GeminiBodyFormat $body;
    private ?string $model = null;
    /** @var list<string> */
    private array $keys = [];

    public function __construct(BatchConfig $config, private readonly GeminiBatchInputMode $mode)
    {
        $this->body = new GeminiBodyFormat($config->inference(), new GeminiMessageFormat());
    }

    #[\Override]
    public function encode(BatchItem $item): array
    {
        $model = $item->request()->model();
        if ($this->model !== null && $this->model !== $model) {
            throw new InvalidArgumentException('Gemini batch input must use one model.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
            throw new InvalidArgumentException('Gemini batch model contains invalid URL path characters.');
        }
        $this->model = $model;
        $this->keys[] = $item->key();
        $request = $this->body->toRequestBody($item->request());

        return match ($this->mode) {
            GeminiBatchInputMode::Inline => ['request' => $request, 'metadata' => ['key' => $item->key()]],
            GeminiBatchInputMode::File => ['key' => $item->key(), 'request' => $request],
        };
    }

    public function model(): string
    {
        return $this->model ?? throw new InvalidArgumentException('Gemini batch input has no model.');
    }

    public function manifest(): BatchManifest
    {
        return new BatchManifest($this->keys);
    }
}
