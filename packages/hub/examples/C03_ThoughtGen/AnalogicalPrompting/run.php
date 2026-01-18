<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Problem {
    public string $problemExplanation;
    public string $solution;
}

class Response {
    /** @var Problem[] */
    public array $relevantProblems;
    public Problem $problemSolution;
    public string $answer;
}

class SolvePerAnalogy {
    private int $n = 3;
    private string $prompt = <<<PROMPT
        <problem>
        {query}
        </problem>
        
        Relevant Problems: Recall {n} relevant and
        distinct problems. For each problem, describe
        it and explain the solution before solving
        the problem    
    PROMPT;

    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: str_replace(['{n}', '{query}'], [$this->n, $query], $this->prompt),
            responseModel: Response::class,
        )->get();
    }
}

$solution = (new SolvePerAnalogy)('What is the area of the square with the four vertices at (-2, 2), (2, -2), (-2, -6), and (-6, -2)?');

dump($solution);
?>
