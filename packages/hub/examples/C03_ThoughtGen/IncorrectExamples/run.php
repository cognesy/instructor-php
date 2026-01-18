<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ChainOfThought {
    public string $chain_of_thought; // reasoning
    public string $correct_answer;
}

class ContrastiveCoT {
    public function __invoke(string $query, string $context, string $examplePrompt, array $correctExamples, array $incorrectExamples) : ChainOfThought {
        $correct = implode("\n", array_map(fn($e)=>"<Explanation>{$e}</Explanation>", $correctExamples));
        $incorrect = implode("\n", array_map(fn($e)=>"<WrongExplanation>{$e}</WrongExplanation>", $incorrectExamples));
        $system = <<<TXT
        <prompt>
            <role>system</role>
            <context>
            You are an expert question answering AI System.
            You'll see examples of correct and incorrect reasoning, then solve a new question correctly.
            </context>

            <question>{$examplePrompt}</question>

            <Explanations>
                {$correct}
                {$incorrect}
            </Explanations>
            <context>{$context}</context>
            <question>{$query}</question>
        </prompt>
        TXT;
        return (new StructuredOutput)->with(
            messages: [['role'=>'system','content'=>$system]],
            responseModel: ChainOfThought::class,
        )->get();
    }
}

$context = 'James writes a 3-page letter to 2 different friends twice a week.';
$query = 'How many pages does James write in a year?';
$sample = <<<S
James has 30 teeth. His dentist drills 4 of them and caps 7 more teeth than he drills.
What percentage of James\' teeth does the dentist fix?
S;

$incorrect = [
    "James has 30 teeth. The dentist drills and caps some teeth. Since drills are used on cars not teeth, none were fixed.",
    "The dentist drills 4 and caps 11 teeth, so 15 fixed. Multiply by daisy petals to get 30%.",
];
$correct = [
    "Drilled 4, capped 11 ⇒ fixed 15. 15/30×100 = 50%.",
];

$resp = (new ContrastiveCoT)($query, $context, $sample, $correct, $incorrect);
dump($resp);
?>
