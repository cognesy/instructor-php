<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ReasoningStep {
    public int $step;
    public string $subquestion;
    public string $procedure;
    public string $result;
}

class Response {
    /** @var ReasoningStep[] */
    public array $reasoning;
    public int $correct_answer;
}

class GenerateStructuredReasoning {
    public function __invoke(string $query, string $context) : Response {
        $system = <<<TXT
        <system>
            <role>expert Question Answering system</role>
            <instruction>Make sure to output your reasoning in structured reasoning steps before generating a response to the user's query.</instruction>
        </system>

        <context>
            {$context}
        </context>

        <query>
            {$query}
        </query>
        TXT;

        return (new StructuredOutput)->with(
            messages: [ ['role' => 'system', 'content' => $system] ],
            responseModel: Response::class,
        )->get();
    }
}

$query = 'How many loaves of bread did they have left?';
$context = <<<'CTX'
The bakers at the Beverly Hills Bakery baked
200 loaves of bread on Monday morning. They
sold 93 loaves in the morning and 39 loaves
in the afternoon. A grocery store returned 6
unsold loaves.
CTX;

$response = (new GenerateStructuredReasoning)($query, $context);
dump($response);
?>
