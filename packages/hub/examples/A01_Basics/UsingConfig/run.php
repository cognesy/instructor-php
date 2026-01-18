<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class User {
    public int $age;
    public string $name;
}

// Get Instructor object with client defined in config.php under 'presets/openai' key
$structuredOutput = (new StructuredOutput)->using('openai');

// Call with custom model and execution mode
$user = $structuredOutput->with(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
)->get();

// Use the results of LLM inference
dump($user);
assert(isset($user->name));
assert(isset($user->age));
?>
