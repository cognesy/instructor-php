<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class FollowUp {
    #[Description("Follow-up question")]
    public string $question;
    #[Description("Answer to the follow-up question")]
    public string $answer;
}

class Response {
    public bool $followUpsRequired;
    /** @var FollowUp[] */
    public array $followUps;
    public string $finalAnswer;
}

class RespondWithFollowUp {
    private $prompt = <<<QUERY
        Query: {query}
        Are follow-up questions needed?
        If so, generate follow-up questions, their answers, and then the final answer to the query.
    QUERY;

    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: Response::class,
        )->get();
    }
}

$response = (new RespondWithFollowUp)(
    query: "Who succeeded the president of France ruling when Bulgaria joined EU?",
);

echo "Answer:\n";
dump($response);
?>
