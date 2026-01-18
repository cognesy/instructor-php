<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Response {
    public string $rephrasedQuestion;
    public string $answer;
}

class Disambiguate {
    private $prompt = <<<PROMPT
        Rephrase and expand the question to address any potential ambiguities, then respond.
        Question: {query}
        PROMPT;

    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: str_replace('{query}', $query, $this->prompt),
            responseModel: Response::class,
        )->get();
    }
}

$response = (new Disambiguate)(query: "What is an object");

dump($response);
?>
