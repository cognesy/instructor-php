<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;

$response = (new Inference)
    ->withDebugPreset('on') // Enable debug mode
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of Brasil']],
        options: ['max_tokens' => 128]
    )
    ->get();

echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: $response\n";
?>
