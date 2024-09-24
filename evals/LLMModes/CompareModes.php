<?php
namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Str;
use Exception;

class CompareModes {
    private float $time;
    private array $exceptions = [];

    public function __construct(
        private string $query,
        private string $expected = 'Paris',
        private array $schema = [],
        private int $maxTokens = 512,
        private bool $debug = false,
    ) {
    }

    private function schema() : array {
        return $this->schema ?: [
            'type' => 'object',
            'description' => 'User data',
            'properties' => [
                'answer' => [
                    'type' => 'string',
                    'description' => 'City',
                ],
            ],
            'required' => ['answer'],
            'additionalProperties' => false,
        ];
    }

    public function executeFor(array $connections, array $modes, array $streamingModes = [false, true]) : void {
        foreach ($modes as $mode) {
            foreach ($connections as $connection) {
                foreach ($streamingModes as $isStreamed) {
                    try {
                        $this->time = microtime(true);
                        $this->callInferenceFor($this->query, $mode, $connection, $this->schema(), $isStreamed);
                    } catch(Exception $e) {
                        $key = $connection.'::'.$mode->value.'::'.($isStreamed?'streamed':'sync');
                        $this->exceptions[$key] = $e;
                        Console::print('          ');
                        Console::println(' !!!!', [Color::BG_RED, Color::BG_YELLOW]);
                    }
                }
            }
        }
        if (!empty($this->exceptions)) {
            Console::println('# EXCEPTIONS:', [Color::BG_RED, Color::BG_YELLOW]);
            foreach($this->exceptions as $key => $exception) {
                $exLine = str_replace("\n", '\n', $exception);
                echo Console::columns([
                    [16, $key, STR_PAD_RIGHT, [Color::DARK_YELLOW]],
                    [100, $exLine, STR_PAD_RIGHT, [Color::GRAY]]
                ], 120);
                Console::println('');
            }
        }
    }

    public function callInferenceFor(string $query, Mode $mode, string $connection, array $schema, bool $isStreamed) : string {
        $this->before($mode, $connection, $isStreamed);
        $inferenceResult = match($mode) {
            Mode::Tools => $this->forModeTools($query, $connection, $schema, $isStreamed),
            Mode::JsonSchema => $this->forModeJsonSchema($query, $connection, $schema, $isStreamed),
            Mode::Json => $this->forModeJson($query, $connection, $schema, $isStreamed),
            Mode::MdJson => $this->forModeMdJson($query, $connection, $schema, $isStreamed),
            Mode::Text => $this->forModeText($query, $connection, $isStreamed),
        };
        $answer = $this->getValue($inferenceResult, $isStreamed);
        $this->after($answer, $mode, $connection, $isStreamed);
        return $answer;
    }

    private function getValue(InferenceResponse $response, bool $isStreamed) : string {
        if (!$isStreamed) {
            return $response->toText();
        }

        $answer = '';
        foreach ($response->toStream() as $chunk) {
            $answer .= $chunk;
        }
        return $answer;
    }

    private function before(Mode $mode, string $connection, bool $isStreamed) : void {
        echo Console::columns([
            [14, $mode->value, STR_PAD_RIGHT, Color::YELLOW],
            [12, $connection, STR_PAD_RIGHT, Color::WHITE],
            [10, $isStreamed ? 'stream' : 'sync', STR_PAD_LEFT, $isStreamed ? Color::BLUE : Color::DARK_BLUE],
        ], 80);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
    }

    private function after(string $answer, Mode $mode, string $connection, bool $isStreamed) : void {
        $delta = $this->timeDeltaInSec();
        $correct = Str::contains($answer, $this->expected);
        $answerLine = str_replace("\n", '\n', $answer);
        echo Console::columns([
            [9, $delta.' sec', STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [5, $correct ? '  OK ' : ' FAIL', STR_PAD_RIGHT, $correct ? [Color::BG_GREEN, Color::WHITE] : [Color::BG_RED, Color::WHITE]],
            [60, ' '.$answerLine, STR_PAD_RIGHT, [Color::WHITE, Color::BG_BLACK]],
        ], 120);
        echo "\n";
    }

    private function forModeTools(string $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: [
                    ['role' => 'user', 'content' => $query]
                ],
                tools: [[
                    'type' => 'function',
                    'description' => 'answer',
                    'function' => [
                        'name' => 'answer',
                        'parameters' => $schema,
                    ],
                ]],
                toolChoice: ['type' => 'function', 'function' => ['name' => 'answer']],
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Tools,
            );
    }

    private function forModeJsonSchema(string $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: [
                    ['role' => 'user', 'content' => $query],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ],
                responseFormat: [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'answer',
                        'schema' => $schema,
                        'strict' => true,
                    ],
                ],
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::JsonSchema,
            );
    }

    private function forModeJson(string $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: [
                    ['role' => 'user', 'content' => $query],
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON.'],
                ],
                responseFormat: [
                    'type' => 'json_object',
                    'schema' => $schema,
                ],
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Json,
            );
    }

    private function forModeMdJson(string $query, string $connection, array $schema, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: [
                    ['role' => 'user', 'content' => $query],
                    ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                    ['role' => 'user', 'content' => 'Respond with correct JSON'],
                    ['role' => 'user', 'content' => '```json'],
                ],
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::MdJson,
            );
    }

    private function forModeText(string $query, string $connection, bool $isStreamed) : InferenceResponse {
        return (new Inference)
            ->withConnection($connection)
            ->withDebug($this->debug)
            ->create(
                messages: [
                    ['role' => 'user', 'content' => $query],
                ],
                options: ['max_tokens' => $this->maxTokens, 'stream' => $isStreamed],
                mode: Mode::Text,
            );
    }

    private function timeDeltaInSec() : string {
        return number_format(microtime(true) - $this->time, 2);
    }
}
