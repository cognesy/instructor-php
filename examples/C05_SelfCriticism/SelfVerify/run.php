<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Candidate {
    /** @var string[] */ public array $reasoning_steps;
    public string $month;
}

class Rewritten { public string $declarative; }

class Verification { public bool $correct; }

class SelfVerifyPipeline {
    public int $n = 3; // number of candidates
    public int $k = 5; // verification count

    public function queryCandidate(string $query) : Candidate {
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: Candidate::class,
            messages: [ ['role' => 'user', 'content' => "Think step by step: {$query}"] ],
        )->get();
    }

    public function rewrite(string $query, Candidate $candidate) : Rewritten {
        $msg = <<<MSG
            Please change the questions and answers into complete declarative sentences
            {$query}
            The answer is {$candidate->month}.
            MSG;
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini', responseModel: Rewritten::class,
            messages: [ ['role' => 'user', 'content' => $msg] ],
        )->get();
    }

    public function verify(string $question) : Verification {
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini', responseModel: Verification::class,
            messages: [ ['role' => 'user', 'content' => $question] ],
        )->get();
    }

    public function run(string $query) : void {
        $candidates = [];
        for ($i = 0; $i < $this->n; $i++) { $candidates[] = $this->queryCandidate($query); }

        foreach ($candidates as $candidate) {
            $rewritten = $this->rewrite($query, $candidate);
            $question = $rewritten->declarative . ' Is this correct? Answer True or False.';

            $score = 0;
            for ($j = 0; $j < $this->k; $j++) {
                $v = $this->verify($question);
                if ($v->correct) { $score++; }
            }
            echo "Candidate: {$candidate->month}, Verification Score: {$score}\n";
        }
    }
}

$query = 'What month is it now if it has been 3 weeks, 10 days, and 2 hours since May 1, 2024 6pm?';
(new SelfVerifyPipeline)->run($query);
?>
