---
title: 'Using CoT to improve interpretation of component data'
docname: 'component_reuse_cot'
---

## Overview

You can reuse the same component for different contexts within a model. In this
example, the TimeRange component is used for both `$workTime` and `$leisureTime`.

We're additionally starting the data structure with a Chain of Thought field
to elicit LLM reasoning for the time range calculation, which can improve
the accuracy of the response.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Instructor;

class TimeRange
{
    /** Step by step reasoning to get the correct time range */
    public string $chainOfThought;
    /** The start time in hours (0-23 format) */
    public int $startTime;
    /** The end time in hours (0-23 format) */
    public int $endTime;
}

$timeRange = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "Workshop with Apex Industries started 9 and it took us 6 hours to complete."]],
    responseModel: TimeRange::class,
    maxRetries: 2
);

dump($timeRange);

assert($timeRange->startTime === 9);
assert($timeRange->endTime === 15);
?>
```
