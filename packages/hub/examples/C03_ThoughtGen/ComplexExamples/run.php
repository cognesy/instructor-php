<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ReasoningStep {
    public int $step;
    public string $subquestion;
    public string $procedure;
    public string $result;
}

class QAResponse {
    /** @var ReasoningStep[] */
    public array $reasoning;
    public int $correct_answer;
}

class ComplexityBasedConsistency {
    public function __invoke(string $query, string $context, int $samples = 5, int $topK = 3) : array {
        $responses = [];
        for ($i = 0; $i < $samples; $i++) {
            $responses[] = $this->generate($query, $context);
        }
        usort($responses, fn($a, $b) => count($b->reasoning) <=> count($a->reasoning));
        return array_slice($responses, 0, $topK);
    }

    private function generate(string $query, string $context) : QAResponse {
        $system = "You are an expert Question Answering system. Output structured reasoning steps before the final answer.";
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => $system . "\n\nContext:\n{$context}\n\nQuery:\n{$query}"],
            ],
            responseModel: QAResponse::class,
        )->get();
    }
}

$query = 'How many loaves of bread did they have left?';
$context = <<<'CTX'
The bakers at the Beverly Hills Bakery baked 200 loaves of bread on Monday morning.
They sold 93 loaves in the morning and 39 loaves in the afternoon. A grocery store returned 6 unsold loaves.
CTX;

$selector = new ComplexityBasedConsistency();
$top = $selector($query, $context, samples: 5, topK: 3);

$counts = [];
foreach ($top as $r) { $a = (string)$r->correct_answer; $counts[$a] = ($counts[$a] ?? 0) + 1; }
$max = max($counts);
$finals = array_keys(array_filter($counts, fn($c) => $c === $max));
$final = $finals[array_rand($finals)];
dump($final);
?>
