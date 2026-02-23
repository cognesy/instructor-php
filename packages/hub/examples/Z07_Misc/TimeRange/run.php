---
title: 'Reusing components'
docname: 'component_reuse'
---

## Overview

You can reuse the same component for different contexts within a model. In this
example, the TimeRange component is used for both `$workTime` and `$leisureTime`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class TimeRange {
    /** The start time in hours. */
    public int $startTime;
    /** The end time in hours. */
    public int $endTime;
}

class UserDetail
{
    public string $name;
    /** Time range during which the user is working. */
    public TimeRange $workTime;
    /** Time range reserved for leisure activities. */
    public TimeRange $leisureTime;
}

$user = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => "Yesterday Jason worked from 9 for 5 hours. Jason then watched a 2 hour movie that ended at 19."]],
    responseModel: UserDetail::class,
    model: 'gpt-4o-mini',
)->get();

dump($user);

assert($user->name === "Jason");
assert($user->workTime->startTime < $user->workTime->endTime);
assert($user->leisureTime->startTime < $user->leisureTime->endTime);
?>
```
