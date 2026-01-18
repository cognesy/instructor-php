<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class RewrittenTask {
    #[Description("Relevant context")]
    public string $relevantContext;
    #[Description("The question from the user")]
    public string $userQuery;
}

class RefineAndSolve {
    private string $prompt = <<<PROMPT
        Given the following text by a user, extract the part
        that is actually relevant to their question. Include
        the actual question or query that the user is asking.
        
        Text by user:
        {query}
        PROMPT;

    public function __invoke(string $problem) : int {
        $rewrittenPrompt = $this->rewritePrompt($problem);
        return (new StructuredOutput)
            ->with(
                messages: "{$rewrittenPrompt->relevantContext}\nQuestion: {$rewrittenPrompt->userQuery}",
                responseModel: Scalar::integer('answer'),
            )
            ->getInt();
    }

    private function rewritePrompt(string $query) : RewrittenTask {
        return (new StructuredOutput)->with(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: RewrittenTask::class,
            model: 'gpt-4o-mini',
        )->get();
    }
}

$answer = (new RefineAndSolve)(problem: <<<PROBLEM
    Mary has 3 times as much candy as Megan.
    Mary then adds 10 more pieces of candy to her collection.
    Max is 5 years older than Mary.
    If Megan has 5 pieces of candy, how many does Mary have in total?
    PROBLEM,
);

echo $answer . "\n";
?>
