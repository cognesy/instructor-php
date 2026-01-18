<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    properties: [
        JsonSchema::string('name', 'City name'),
        JsonSchema::integer('population', 'City population'),
        JsonSchema::integer('founded', 'Founding year'),
    ],
    requiredProperties: ['name', 'population', 'founded'],
);

$response = (new Inference)
    ->using('openai')
    //->withDebugPreset('on')
    ->with(
        messages: [
            ['role' => 'user', 'content' => 'What is capital of France? Respond with function call.']
        ],
        tools: [
            $schema->toFunctionCall(
               functionName: 'provide_data',
               functionDescription: 'Provide city data'
            )
        ],
        toolChoice: [
            'type' => 'function',
            'function' => [
                'name' => 'provide_data'
            ]
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::Tools,
    )
    ->response();

$data = $response->findJsonData(OutputMode::Tools)->toArray();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data));
assert(is_string($data['name']));
assert(is_int($data['population']));
assert(is_int($data['founded']));
?>
