<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class Subquestion {
    public function __construct(
        public string $question
    ) {}
}

class Answer {
    public function __construct(
        public int $answer
    ) {}
}

class SubquestionWithAnswer {
    public function __construct(
        public string $question,
        public int $answer
    ) {}
}

class LeastToMostSolver {
    public function __invoke(string $question): array {
        $subquestions = $this->decompose($question);
        return $this->solveSequentially($subquestions, $question);
    }
    
    private function decompose(string $question): array {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user', 
                    'content' => "Break this question down into subquestions to solve sequentially: {$question}"
                ],
            ],
            responseModel: Sequence::of(Subquestion::class),
        )->get()->toArray();
    }
    
    private function solve(string $question, array $solvedQuestions, string $originalQuestion): int {
        $solvedContext = '';
        foreach ($solvedQuestions as $solved) {
            $solvedContext .= "{$solved->question} {$solved->answer}\n";
        }
        
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'user',
                    'content' => <<<PROMPT
                        <original_question>
                        {$originalQuestion}
                        </original_question>
                        
                        <solved_subquestions>
                        {$solvedContext}
                        </solved_subquestions>
                        
                        Solve this next subquestion: {$question}
                    PROMPT,
                ],
            ],
            responseModel: Answer::class,
        )->get()->answer;
    }
    
    private function solveSequentially(array $subquestions, string $originalQuestion): array {
        $solvedQuestions = [];
        
        foreach ($subquestions as $subquestion) {
            $answer = $this->solve($subquestion->question, $solvedQuestions, $originalQuestion);
            $solvedQuestions[] = new SubquestionWithAnswer($subquestion->question, $answer);
        }
        
        return $solvedQuestions;
    }
}

$results = (new LeastToMostSolver)(
    "Four years ago, Kody was only half as old as Mohamed. If Mohamed is currently twice 30 years old, how old is Kody?"
);

foreach ($results as $result) {
    echo "{$result->question} {$result->answer}\n";
}

dump($results);
?>
