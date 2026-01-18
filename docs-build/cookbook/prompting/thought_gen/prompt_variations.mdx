<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class PromptTemplate {
    public string $prompt_template;
}

class GeneratePromptTemplates {
    public function __invoke(string $prompt) : array {
        $system = 'You are an expert prompt miner that generates 3 clearer, concise prompt templates.';
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => $system],
                ['role' => 'system', 'content' => $prompt],
            ],
            responseModel: Sequence::of(PromptTemplate::class),
        )->get()->toArray();
    }
}

$prompt = 'France is the capital of Paris';
$templates = (new GeneratePromptTemplates)($prompt);
dump($templates);
?>
