<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = (new Inference)
    ->using('openai')
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of France? \
           Respond with a JSON object in a ```json``` code block containing "name", "population", and "founded". \
           Use integer values for population and founded year (negative for BC). Do not include extra text. \
           Example: {"name": "Berlin", "population": 3700000, "founded": 1237}']],
        options: ['max_tokens' => 64, 'temperature' => 0],
        mode: OutputMode::MdJson,
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
