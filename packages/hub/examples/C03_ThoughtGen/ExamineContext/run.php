---
title: 'Examine The Context'
docname: 'examine_context'
---

## Overview

Encouraging the model to examine each source in context helps mitigate irrelevant information and improves reasoning quality. This is known as Thread of Thought.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class ThreadOfThoughtResponse {
    /** @var string[] */
    public array $analysis; // explanations for each relevant source
    public int $correct_answer;
}

class ThreadOfThought {
    public function __invoke(string $query, array $context) : ThreadOfThoughtResponse {
        $sources = implode("\n", $context);
        $system = <<<TXT
        You are an expert Question Answerer.
        Here are the sources you should refer to for context:
        {$sources}
        TXT;
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $query],
                ['role' => 'assistant', 'content' => 'Navigate through the context incrementally, identifying and summarizing relevant portions.'],
            ],
            responseModel: ThreadOfThoughtResponse::class,
        )->get();
    }
}

$context = [
    'The price of a house was $100,000 in 2024',
    'The Great Wall of China is not visible from space with the naked eye',
    'Honey never spoils; archaeologists found 3,000-year-old edible honey in Egyptian tombs',
    "The world's oldest known living tree is over 5,000 years old and is located in California",
    'The price of a house was $80,000 in 2023',
];
$query = 'What was the increase in the price of a house from 2023 to 2024?';
$response = (new ThreadOfThought)($query, $context);
dump($response);
?>
```

## Useful Tips

Here are some alternative phrases that you can add to your prompt to generate a thread of thought before your model generates a response.

- In a step-by-step manner, go through the context, surfacing important information that could be useful.
- Walk me through this lengthy document segment by segment, focusing on each part's significance.
- Guide me through the context part by part, providing insights along the way.
- Divide the document into manageable parts and guide me through each one, providing insights as we move along.
- Let's go through this document piece by piece, paying close attention to each section.
- Take me through the context bit by bit, making sure we capture all important aspects.
- Examine the document in chunks, evaluating each part critically before moving to the next.
- Analyze the context by breaking it down into sections, summarizing each as we move forward.
- Navigate through the context incrementally, identifying and summarizing relevant portions.
- Proceed through the context systematically, zeroing in on areas that could provide the answers we're seeking.
- Take me through this long document step-by-step, making sure not to miss any important details.
- Analyze this extensive document in sections, summarizing each one and noting any key points.
- Navigate through this long document by breaking it into smaller parts and summarizing each, so we don't miss anything.
- Let's navigate through the context section by section, identifying key elements in each part.
- Let's dissect the context into smaller pieces, reviewing each one for its importance and relevance.
- Carefully analyze the context piece by piece, highlighting relevant points for each question.
- Read the context in sections, concentrating on gathering insights that answer the question at hand.
- Let's read through the document section by section, analyzing each part carefully as we go.
- Let's dissect this document bit by bit, making sure to understand the nuances of each section.
- Systematically work through this document, summarizing and analyzing each portion as we go.
- Let's explore the context step-by-step, carefully examining each segment.
- Systematically go through the context, focusing on each part individually.
- Methodically examine the context, focusing on key segments that may answer the query.
- Progressively sift through the context, ensuring we capture all pertinent details.
- Take a modular approach to the context, summarizing each part before drawing any conclusions.
- Examine each segment of the context meticulously, and let's discuss the findings.
- Approach the context incrementally, taking the time to understand each portion fully.
- Let's scrutinize the context in chunks, keeping an eye out for information that answers our queries.
- Walk me through this context in manageable parts step by step, summarizing and analyzing as we go.
- Let's take a segmented approach to the context, carefully evaluating each part for its relevance to the questions posed.

### References

1) Thread of Thought Unraveling Chaotic Contexts (https://arxiv.org/pdf/2311.08734)
