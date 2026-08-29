<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateReasoning;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use InvalidArgumentException;

/** Applies typed reasoning once at the provider request-body boundary. */
final readonly class ReasoningBodyFormat implements CanMapRequestBody
{
    private const REASONING_KEYS = [
        'reasoning_effort',
        'reasoning',
        'thinking',
        'thinkingConfig',
        'enable_thinking',
        'thinking_budget',
        'reasoning_budget',
    ];

    public function __construct(
        private CanMapRequestBody $bodyFormat,
        private CanTranslateReasoning $translator,
        private string $defaultModel = '',
    ) {}

    public function toRequestBody(InferenceRequest $request): array
    {
        $body = $this->bodyFormat->toRequestBody($request);
        $selection = $request->reasoning();
        if ($selection->isDefault()) {
            return $body;
        }

        $this->assertNoRawReasoning($request->options());
        $this->assertNoRawReasoning($body);

        $model = $request->model() ?: $this->defaultModel;
        $translation = $this->translator->translate($model, $selection);

        return array_replace_recursive($body, $translation->options->toArray());
    }

    private function assertNoRawReasoning(array $options): void
    {
        foreach (array_keys($options) as $key) {
            if (in_array((string) $key, self::REASONING_KEYS, true)) {
                throw new InvalidArgumentException(
                    "Typed reasoning conflicts with raw option {$key}.",
                );
            }

        }
    }
}
