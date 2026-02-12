---
title: 'Use LLMs to Combine Different Responses'
docname: 'combine_responses'
id: 'f34d'
---
## Overview

Universal Self-Consistency uses a second LLM to judge the quality of multiple responses to a query and select the most consistent one.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ResponseItem { public string $chain_of_thought; public string $answer; }
class SelectedResponse { public int $most_consistent_response_id; }

class CombineResponses {
    public function __invoke(string $query, int $k = 3) : ResponseItem {
        $responses = [];
        for ($i = 0; $i < $k; $i++) { $responses[] = $this->generate($query); }
        $sel = $this->select($responses, $query);
        $idx = max(0, min($sel->most_consistent_response_id, count($responses)-1));
        return $responses[$idx];
    }

    private function generate(string $query) : ResponseItem {
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user', 'content'=>$query] ],
            responseModel: ResponseItem::class,
        )->get();
    }

    private function select(array $responses, string $query) : SelectedResponse {
        $formatted = [];
        foreach ($responses as $i => $r) { $formatted[] = "Response {$i}: {$r->chain_of_thought}. {$r->answer}"; }
        $content = "<user query>\n{$query}\n</user query>\n\n" . implode("\n", $formatted) . "\n\nEvaluate these responses. Select the most consistent response based on majority consensus.";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user','content'=>$content] ],
            responseModel: SelectedResponse::class,
        )->get();
    }
}

$query = "The three-digit number 'ab5' is divisible by 3. How many different three-digit numbers can 'ab5' represent?";
$result = (new CombineResponses)($query, k: 3);
dump($result);
?>
```

### References

1) Universal Self-Consistency For Large Language Model Generation (https://arxiv.org/pdf/2311.17311)
