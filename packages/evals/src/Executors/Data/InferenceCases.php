<?php

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Config\Settings;
use Cognesy\Evals\Utils\Combination;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Generator;

class InferenceCases
{
    private array $presets = [];
    private array $modes = [];
    private array $stream = [];

    private function __construct(
        array $presets = [],
        array $modes = [],
        array $stream = [],
    ) {
        $this->presets = $presets;
        $this->modes = $modes;
        $this->stream = $stream;
    }

    public static function all() : Generator {
        return (new self)->initiateWithAll()->make();
    }

    public static function except(
        array $presets = [],
        array $modes = [],
        array $stream = [],
    ) : Generator {
        $instance = (new self)->initiateWithAll();
        $instance->presets = match(true) {
            [] === $presets => $instance->presets,
            default => array_diff($instance->presets, $presets),
        };
        $instance->modes = match(true) {
            [] === $modes => $instance->modes,
            default => array_filter($instance->modes, fn($mode) => !$mode->isIn($modes)),
        };
        $instance->stream = match(true) {
            [] === $stream => $instance->stream,
            default => array_diff($instance->stream, $stream),
        };
        return $instance->make();
    }

    public static function only(
        array $presets = [],
        array $modes = [],
        array $stream = [],
    ) : Generator {
        $instance = (new self)->initiateWithAll();
        $instance->presets = match(true) {
            [] === $presets => $instance->presets,
            default => array_intersect($instance->presets, $presets),
        };
        $instance->modes = match(true) {
            [] === $modes => $instance->modes,
            default => array_filter($instance->modes, fn($mode) => $mode->isIn($modes)),
        };
        $instance->stream = match(true) {
            [] === $stream => $instance->stream,
            default => array_intersect($instance->stream, $stream),
        };
        return $instance->make();
    }

    // INTERNAL //////////////////////////////////////////////////

    private function initiateWithAll() : self {
        return new self(
            presets: $this->presets(),
            modes: $this->modes(),
            stream: $this->streamingModes(),
        );
    }

    /**
     * @return Generator<array>
     */
    private function make() : Generator {
        return Combination::generator(
            mapping: InferenceCaseParams::class,
            sources: [
                'isStreamed' => $this->stream ?: $this->streamingModes(),
                'mode' => $this->modes ?: $this->modes(),
                'preset' => $this->presets ?: $this->presets(),
            ],
        );
    }

    private function presets() : array {
        $presets = Settings::get(LLMConfig::group(), 'presets', []);
        return array_keys($presets);
//        return [
//            'azure',
//            'cohere1',
//            'cohere2',
//            'fireworks',
//            'gemini',
//            'groq',
//            'mistral',
//            'ollama',
//            'openai',
//            'openrouter',
//            'together',
//        ];
    }

    private function streamingModes() : array {
        return [
            true,
            false,
        ];
    }

    private function modes() : array {
        return [
            OutputMode::Text,
            OutputMode::MdJson,
            OutputMode::Json,
            OutputMode::JsonSchema,
            OutputMode::Tools,
            OutputMode::Unrestricted,
        ];
    }
}