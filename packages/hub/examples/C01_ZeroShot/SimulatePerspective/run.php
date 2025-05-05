---
title: 'Simulate a Perspective'
docname: 'simulate_perspective'
---

## Overview

How can we encourage the model to focus on relevant information?

SimToM (Simulated Theory of Mind) is a two-step prompting technique that
encourages a model to consider a specific perspective.

This can be useful for complex questions with multiple entities. For example,
if the prompt contains information about two individuals, we can ask the
model to answer our query from the perspective of one of the individuals.

This is implemented in two steps. Given an entity:
 - Identify and isolate information relevant to the entity
 - Ask the model to answer the query from the entity's perspective

<Tip>
### Sample Template

 - Step 1:
   - Given the following context, list the facts that `{entity}` would know.
   - Context: `{context}`
 - Step 2:
   - You are `{entity}`.
   - Answer the following question based only on these facts you know: `{facts}`.
   - Question: `{query}`
</Tip>

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Features\Schema\Attributes\Description;
use Cognesy\Instructor\Instructor;
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
        return (new Instructor)->respond(
            messages: str_replace(
                ['{context}', '{query}', '{entity}'],
                [$context, $query, $entity],
                $this->extractionPrompt
            ),
            responseModel: KnownFacts::class,
        )->facts;
    }

    private function answerQuestion(string $entity, string $query, array $knownFacts) : string {
        $knowledge = Arrays::toBullets($knownFacts);

        return (new Instructor)->respond(
            messages: str_replace(
                ['{entity}', '{knowledge}', '{query}'],
                [$entity, $knowledge, $query],
                $this->povPrompt
            ),
            responseModel: Scalar::string('location'),
        );
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
```

## References

 1. [Think Twice: Perspective-Taking Improves Large Language Models' Theory-of-Mind Capabilities](https://arxiv.org/abs/2311.10227)