<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = (new Inference)
    ->using('openai') // optional, default is set in /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
           Respond with JSON data containing name", population and year of founding. \
           Example: {"name": "Berlin", "population": 3700000, "founded": 1237}']],
        responseFormat: [
            'type' => 'json_object',
        ],
        options: ['max_tokens' => 64],
        mode: OutputMode::Json,
    )
    ->asJsonData();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data), 'Response should be an array');
assert(isset($data['name']), 'Response should have "name" field');
assert(strpos($data['name'], 'Paris') !== false, 'City name should be Paris');
assert(isset($data['population']), 'Response should have "population" field');
assert(isset($data['founded']), 'Response should have "founded" field');
?>
