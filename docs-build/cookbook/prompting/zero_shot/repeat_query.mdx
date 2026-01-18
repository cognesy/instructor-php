<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class Response {
    #[Description("Repeat user's query.")]
    public string $query;
    #[Description("Let's think step by step.")]
    public string $thoughts;
    public int $answer;
}

class RereadAndRespond {
    public function __invoke(string $query) : Response {
        return (new StructuredOutput)->with(
            messages: $query,
            responseModel: Response::class,
        )->get();
    }
}

$response = (new RereadAndRespond)(
    query: <<<QUERY
        Roger has 5 tennis balls. He buys 2 more cans of tennis balls.
        Each can has 3 tennis balls.
        How many tennis balls does he have now?
    QUERY,
);

echo "Answer:\n";
dump($response);
?>
