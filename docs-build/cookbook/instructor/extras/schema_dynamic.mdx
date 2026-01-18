<?php
require 'examples/boot.php';

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$city = Structure::define('city', [
    Field::string('name', 'City name')->required(),
    Field::int('population', 'City population')->required(),
    Field::int('founded', 'Founding year')->required(),
]);

$data = (new StructuredOutput)
    ->using('openai')
    //->withDebugPreset('on')
    ->intoArray()
    ->withMessages([['role' => 'user', 'content' => 'What is capital of France? \
        Respond with JSON data.']])
    ->withResponseJsonSchema($city->toJsonSchema())
    ->withOptions(['max_tokens' => 64])
    ->withOutputMode(OutputMode::JsonSchema)
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT:\n";
dump($data);

assert(is_array($data), 'Response should be an array');
assert(isset($data['name']), 'Response should have "name" field');
assert(strpos($data['name'], 'Paris') !== false, 'City name should be Paris');
assert(isset($data['population']), 'Response should have "population" field');
assert(isset($data['founded']), 'Response should have "founded" field');
?>
