<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class ThreadOfThoughtResponse {
    /** @var string[] */
    public array $analysis; // explanations for each relevant source
    public int $correct_answer;
}

class ThreadOfThought {
    public function __invoke(string $query, array $context) : ThreadOfThoughtResponse {
        $sources = implode("\n", $context);
        $system = <<<TXT
        You are an expert Question Answerer.
        Here are the sources you should refer to for context:
        {$sources}
        TXT;
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $query],
                ['role' => 'assistant', 'content' => 'Navigate through the context incrementally, identifying and summarizing relevant portions.'],
            ],
            responseModel: ThreadOfThoughtResponse::class,
        )->get();
    }
}

$context = [
    'The price of a house was $100,000 in 2024',
    'The Great Wall of China is not visible from space with the naked eye',
    'Honey never spoils; archaeologists found 3,000-year-old edible honey in Egyptian tombs',
    "The world's oldest known living tree is over 5,000 years old and is located in California",
    'The price of a house was $80,000 in 2023',
];
$query = 'What was the increase in the price of a house from 2023 to 2024?';
$response = (new ThreadOfThought)($query, $context);
dump($response);
?>
