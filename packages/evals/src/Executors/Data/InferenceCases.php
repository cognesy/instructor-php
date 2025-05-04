<?php

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Evals\Utils\Combination;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Settings;
use Generator;

class InferenceCases
{
    private array $connections = [];
    private array $modes = [];
    private array $stream = [];

    private function __construct(
        array $connections = [],
        array $modes = [],
        array $stream = [],
    ) {
        $this->connections = $connections;
        $this->modes = $modes;
        $this->stream = $stream;
    }

    public static function all() : Generator {
        return (new self)->initiateWithAll()->make();
    }

    public static function except(
        array $connections = [],
        array $modes = [],
        array $stream = [],
    ) : Generator {
        $instance = (new self)->initiateWithAll();
        $instance->connections = match(true) {
            [] === $connections => $instance->connections,
            default => array_diff($instance->connections, $connections),
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
        array $connections = [],
        array $modes = [],
        array $stream = [],
    ) : Generator {
        $instance = (new self)->initiateWithAll();
        $instance->connections = match(true) {
            [] === $connections => $instance->connections,
            default => array_intersect($instance->connections, $connections),
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
            connections: $this->connections(),
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
                'connection' => $this->connections ?: $this->connections(),
            ],
        );
    }

    private function connections() : array {
        $connections = Settings::get('llm', 'connections', []);
        return array_keys($connections);
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