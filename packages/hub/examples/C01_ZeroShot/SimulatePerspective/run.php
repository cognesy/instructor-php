<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Arrays;

class KnownFacts {
    #[Description("Facts that the given entity would know")]
    /** @var string[] */
    public array $facts;
}

class SimulatePerspective {
    private string $extractionPrompt = <<<PROMPT
        Given the following context, list
        the facts that {entity} would know:
        
        Context:
        {context}
        {query}
        
        List only the facts relevant to {entity}.
        PROMPT;

    private $povPrompt = <<<PROMPT
        You are {entity}. Answer the following question
        based only on these facts you know:
        {knowledge}
        
        Question: {query}
        PROMPT;

    public function __invoke(string $context, string $query, string $perspective) : string {
        $knownFacts = $this->getKnownFacts($context, $query, $perspective);
        return $this->answerQuestion($perspective, $query, $knownFacts);
    }

    private function getKnownFacts(string $context, string $query, string $entity) : array {
        return (new StructuredOutput)->with(
            messages: str_replace(
                ['{context}', '{query}', '{entity}'],
                [$context, $query, $entity],
                $this->extractionPrompt
            ),
            responseModel: KnownFacts::class,
        )->get()->facts;
    }

    private function answerQuestion(string $entity, string $query, array $knownFacts) : string {
        $knowledge = Arrays::toBullets($knownFacts);

        return (new StructuredOutput)->with(
                messages: str_replace(
                    ['{entity}', '{knowledge}', '{query}'],
                    [$entity, $knowledge, $query],
                    $this->povPrompt
                ),
                responseModel: Scalar::string('location'),
            )
            ->getString();
    }
}

$povEntity = "Alice";

$location = (new SimulatePerspective)(
    context: <<<CONTEXT
        Alice puts the book on the table.
        Alice leaves the room.
        Bob moves the book to the shelf.
    CONTEXT,
    query: "Where does $povEntity think the book is?",
    perspective: $povEntity,
);
?>
