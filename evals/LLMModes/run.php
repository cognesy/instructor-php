<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Extras\LLM\InferenceResponse;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Str;

//$connections = ['anthropic', 'azure', 'cohere', 'fireworks', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'openrouter', 'together'];
$streamingModes = [false];
$connections = ['groq'];
$modes = [
    Mode::Tools,
    Mode::JsonSchema,
    Mode::Json,
    Mode::MdJson,
    Mode::Text
];

(new CompareModes)->executeFor(
    query: 'Jason is 28 years old',
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);

class CompareModes {
    private bool $debug = true;
    private int $maxTokens = 512;

    private function schema() : array {
        return [
            'type' => 'object',
            'properties' => [
                'answer' => [
                    'type' => 'int',
                    'description' => 'Age',
                ],
            ],
            'required' => ['answer'],
            'additionalProperties' => false,
        ];
    }

    public function executeFor(string $query, array $connections, array $modes, array $streamingModes = [false, true]) : void {
        foreach ($modes as $mode) {
            foreach ($connections as $connection) {
                foreach ($streamingModes as $isStreamed) {
                    $this->callInferenceFor($query, $mode, $connection, $this->schema(), $isStreamed);
                }
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
            //[6, 'MODE:', STR_PAD_LEFT, Color::DARK_GRAY],
            [14, $mode->value, STR_PAD_RIGHT, Color::YELLOW],
            //[12, 'CONNECTION:', STR_PAD_LEFT, Color::DARK_GRAY],
            [12, $connection, STR_PAD_RIGHT, Color::WHITE],
            [10, $isStreamed ? 'stream' : 'sync', STR_PAD_LEFT, $isStreamed ? Color::BLUE : Color::DARK_BLUE],
        ], 80);
        Console::print('', [Color::GRAY, Color::BG_BLACK]);
//        Console::print('# ');
//        Console::print('MODE: ', Color::DARK_GRAY);
//        Console::print($mode->value, Color::YELLOW);
//        Console::print(' # CONNECTION: ', Color::DARK_GRAY);
//        Console::print($connection, Color::WHITE);
//        Console::print(' # ');
//        Console::print($isStreamed ? 'streamed' : 'non-streamed', Color::DARK_BLUE);
//        Console::println("");
    }

    private function after(string $answer, Mode $mode, string $connection, bool $isStreamed) : void {
        $correct = Str::contains($answer, 'Paris');
        // escape \n
        $answerLine = str_replace("\n", '\n', $answer);
        echo Console::columns([
//            [6, 'MODE:', STR_PAD_LEFT, Color::DARK_GRAY],
//            [8, $mode->value, STR_PAD_LEFT, Color::YELLOW],
//            [12, 'CONNECTION:', STR_PAD_LEFT, Color::DARK_GRAY],
//            [10, $connection, STR_PAD_LEFT, Color::WHITE],
//            [10, $isStreamed ? 'stream' : 'sync', STR_PAD_LEFT, $isStreamed ? Color::BLUE : Color::DARK_BLUE],
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
}



//$mode = Mode::Tools;
//$isStreamed = false;
//foreach ($connections as $connection) {
//    before($mode, $connection, $isStreamed);
//    $answer = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?']
//            ],
//            tools: [[
//                'type' => 'function',
//                'function' => [
//                    'name' => 'answer',
//                    'parameters' => $schema,
//                ],
//            ]],
//            toolChoice: ['type' => 'function', 'function' => ['name' => 'answer']],
//            options: ['max_tokens' => 64, 'stream' => $isStreamed],
//            mode: $mode,
//        )
//        ->toText();
//    after($answer);
//}
//
//$isStreamed = true;
//foreach ($connections as $connection) {
//    before($mode, $connection, $isStreamed);
//    $answerGen = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?']
//            ],
//            tools: [[
//                'type' => 'function',
//                'function' => [
//                    'name' => 'answer',
//                    'parameters' => $schema,
//                ],
//            ]],
//            toolChoice: ['type' => 'function', 'function' => ['name' => 'answer']],
//            options: ['stream' => true, 'max_tokens' => 64],
//            mode: $mode,
//        )
//        ->toStream();
//    $answer = '';
//    foreach ($answerGen as $chunk) {
//        $answer .= $chunk;
//    }
//    after($answer);
//}
//
//$mode = Mode::JsonSchema;
//$isStreamed = false;
//foreach ($connections as $connection) {
//    $answer = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
//            ],
//            responseFormat: [
//                'type' => 'json_schema',
//                'json_schema' => [
//                    'name' => 'answer',
//                    'schema' => $schema,
//                    'strict' => true,
//                ],
//            ],
//            options: ['max_tokens' => 64],
//            mode: Mode::JsonSchema,
//        )
//        ->toText();
//
//    echo "[$connection]\n$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//echo "# STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answerGen = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
//            ],
//            responseFormat: [
//                'type' => 'json_schema',
//                'json_schema' => [
//                    'name' => 'answer',
//                    'schema' => $schema,
//                    'strict' => true,
//                ],
//            ],
//            options: ['stream' => true, 'max_tokens' => 64],
//            mode: Mode::JsonSchema,
//        )
//        ->toStream();
//
//    echo "[$connection]\n";
//    $answer = '';
//    foreach ($answerGen as $chunk) {
//        $answer .= $chunk;
//    }
//    echo "$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//
//
//echo "### JSON MODE\n\n";
//
//echo "# NON-STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answer = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
//                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
//            ],
//            responseFormat: [
//                'type' => 'json_object',
//                'schema' => $schema,
//            ],
//            options: ['max_tokens' => 64],
//            mode: Mode::Json,
//        )
//        ->toText();
//
//    echo "[$connection]\n$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//echo "# STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answerGen = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
//                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
//            ],
//            responseFormat: [
//                'type' => 'json_object',
//                'schema' => $schema,
//            ],
//            options: ['stream' => true, 'max_tokens' => 64],
//            mode: Mode::Json,
//        )
//        ->toStream();
//
//    echo "[$connection]\n";
//    $answer = '';
//    foreach ($answerGen as $chunk) {
//        $answer .= $chunk;
//    }
//    echo "$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//
//
//echo "### MD_JSON MODE\n\n";
//
//echo "# NON-STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answer = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
//                ['role' => 'user', 'content' => 'Respond with correct JSON'],
//                ['role' => 'user', 'content' => '```json'],
//            ],
//            options: ['max_tokens' => 64],
//            mode: Mode::MdJson,
//        )
//        ->toText();
//
//    echo "[$connection]\n$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//echo "# STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answerGen = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
//                ['role' => 'user', 'content' => 'Respond with correct JSON'],
//                ['role' => 'user', 'content' => '```json'],
//            ],
//            options: ['stream' => true, 'max_tokens' => 64],
//            mode: Mode::MdJson,
//        )
//        ->toStream();
//
//    echo "[$connection]\n";
//    $answer = '';
//    foreach ($answerGen as $chunk) {
//        $answer .= $chunk;
//    }
//    echo "$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//
//echo "### TEXT MODE\n\n";
//
//echo "# NON-STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answer = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//            ],
//            options: ['max_tokens' => 64],
//            mode: Mode::Text,
//        )
//        ->toText();
//
//    echo "[$connection]\n$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
//
//echo "# STREAMED INFERENCE:\n\n";
//
//foreach ($connections as $connection) {
//    $answerGen = (new Inference)
//        ->withConnection($connection)
//        ->create(
//            messages: [
//                ['role' => 'user', 'content' => 'What is capital of France?'],
//            ],
//            options: ['stream' => true, 'max_tokens' => 64],
//            mode: Mode::Text,
//        )
//        ->toStream();
//
//    echo "[$connection]\n";
//    $answer = '';
//    foreach ($answerGen as $chunk) {
//        $answer .= $chunk;
//    }
//    echo "$answer\n\n";
//    assert(Str::contains($answer, 'Paris'));
//}
