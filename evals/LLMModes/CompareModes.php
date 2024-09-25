<?php
namespace Cognesy\Evals\LLMModes;

use Closure;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Exception;

class CompareModes {
    private array $exceptions = [];
    private array $responses = [];

    public function __construct(
        private string $query,
        private Closure $evalFn,
        private array $schema = [],
        private int $maxTokens = 512,
        private bool $debug = false,
    ) {}

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

    private function tools() : array {
        return [[
            'type' => 'function',
            'description' => 'answer',
            'function' => [
                'name' => 'answer',
                'parameters' => $this->schema,
            ],
        ]];
    }

    private function responseFormatJsonSchema() : array {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'answer',
                'schema' => $this->schema,
                'strict' => true,
            ],
        ];
    }

    private function responseFormatJson() : array {
        return [
            'type' => 'json_object',
            'schema' => $this->schema,
        ];
    }

    private function toolChoice() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'answer'
            ]
        ];
    }

    public function executeAll(array $connections, array $modes, array $streamingModes = [false, true]) : array {
        foreach ($modes as $mode) {
            foreach ($connections as $connection) {
                foreach ($streamingModes as $isStreamed) {
                    $this->before($mode, $connection, $isStreamed);
                    $evalResponse = $this->execute($connection, $mode, $isStreamed);
                    $this->after($evalResponse->answer, $evalResponse->isCorrect, $evalResponse->timeElapsed);
                }
            }
        }

        if (!empty($this->exceptions)) {
            Console::println('');
            Console::println(' EXCEPTIONS ', [Color::BG_RED, Color::YELLOW]);
            foreach($this->exceptions as $key => $exception) {
                $exLine = str_replace("\n", '\n', $exception);
                echo Console::columns([
                    [30, $key, STR_PAD_RIGHT, [Color::DARK_YELLOW]],
                    [100, $exLine, STR_PAD_RIGHT, [Color::GRAY]]
                ], 120);
                Console::println('');
            }
            Console::println('');
        }

        return $this->responses;
    }

    private function execute(string $connection, Mode $mode, bool $isStreamed) : EvalResponse {
        $key = $this->makeKey($connection, $mode, $isStreamed);
        try {
            $time = microtime(true);
            $answer = $this->callInferenceFor($this->query, $mode, $connection, $this->schema(), $isStreamed);
            $timeElapsed = microtime(true) - $time;
            $isCorrect = ($this->evalFn)(new EvalRequest(
                $answer, $this->query, $this->schema(), $mode, $connection, $isStreamed
            ));
            $evalResponse = new EvalResponse(
                id: $key,
                answer: $answer,
                isCorrect: $isCorrect,
                timeElapsed: $timeElapsed,
            );
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->exceptions[$key] = $e;
            $evalResponse = new EvalResponse(
                id: $key,
                answer: '',
                isCorrect: false,
                timeElapsed: $timeElapsed,
                exception: $e,
            );
            Console::print('          ');
            Console::print(' !!!! ', [Color::RED, Color::BG_BLACK]);
            Console::println($this->exc2txt($e, 80), [Color::RED, Color::BG_BLACK]);
        }
        $this->responses[] = $evalResponse;
        return $evalResponse;
    }

    public function callInferenceFor(string $query, Mode $mode, string $connection, array $schema, bool $isStreamed) : string {
        $inferenceResult = match($mode) {
            Mode::Tools => $this->forModeTools($query, $connection, $schema, $isStreamed),
            Mode::JsonSchema => $this->forModeJsonSchema($query, $connection, $schema, $isStreamed),
            Mode::Json => $this->forModeJson($query, $connection, $schema, $isStreamed),
            Mode::MdJson => $this->forModeMdJson($query, $connection, $schema, $isStreamed),
            Mode::Text => $this->forModeText($query, $connection, $isStreamed),
        };
        $answer = $this->getValue($inferenceResult, $isStreamed);
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

    private function after(string $answer, bool $isCorrect, float $timeElapsed) : void {
        $answerLine = str_replace("\n", '\n', $answer);
        echo Console::columns([
            [9, $this->timeFormat($timeElapsed), STR_PAD_LEFT, [Color::DARK_YELLOW]],
            [5, $isCorrect ? '  OK ' : ' FAIL', STR_PAD_RIGHT, $isCorrect ? [Color::BG_GREEN, Color::WHITE] : [Color::BG_RED, Color::WHITE]],
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
                tools: $this->tools(),
                toolChoice: $this->toolChoice(),
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
                responseFormat: $this->responseFormatJsonSchema(),
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
                responseFormat: $this->responseFormatJson(),
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

    private function timeFormat(float $time) : string {
        return number_format($time, 2)
            . ' sec';
    }

    private function exc2txt(Exception $e, int $maxLen) : string {
        return ' '
            . substr(str_replace("\n", '\n', $e->getMessage()), 0, $maxLen)
            . '...';
    }

    private function makeKey(string $connection, Mode $mode, bool $isStreamed) : string {
        return $connection.'::'.$mode->value.'::'.($isStreamed?'streamed':'sync');
    }
}
