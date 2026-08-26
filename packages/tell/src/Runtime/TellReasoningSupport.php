<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Tell\TellReasoningEffort;
use InvalidArgumentException;

/** Validates and translates Tell reasoning intent at the Polyglot boundary. */
final readonly class TellReasoningSupport
{
    public static function assertSupported(string $driver, string $model, ?TellReasoningEffort $effort): void
    {
        if ($effort === null || self::supports($driver, $model)) {
            return;
        }

        $label = $model === '' ? $driver : "{$driver}/{$model}";
        throw new InvalidArgumentException(
            "Reasoning effort is not supported by '{$label}' according to Polyglot capability metadata.",
        );
    }

    /** @return array<string, mixed> */
    public static function options(string $driver, TellReasoningEffort $effort): array
    {
        return match ($driver) {
            'deepseek' => [
                'thinking' => ['type' => 'enabled'],
                'reasoning_effort' => $effort->value,
            ],
            'qwen' => [
                'thinking' => true,
                'reasoning_effort' => $effort->value,
            ],
            default => ['reasoning_effort' => $effort->value],
        };
    }

    public static function supports(string $driver, string $model): bool
    {
        $capabilities = BundledInferenceDrivers::capabilities($driver, $model);
        if ($capabilities === null) {
            return false;
        }

        // Tell 2.8.x still permits Polyglot 2.8.3, predating this capability
        // accessor. Retain the same narrow model rules for that install shape.
        if (is_callable([$capabilities, 'supportsReasoningEffort'])) {
            return (bool) call_user_func([$capabilities, 'supportsReasoningEffort']);
        }

        return match ($driver) {
            'deepseek' => preg_match('/^deepseek-v4-(?:flash|pro|flash-vision-exp)$/', $model) === 1,
            'qwen' => preg_match('/^qwen3\./', $model) === 1,
            default => false,
        };
    }
}
