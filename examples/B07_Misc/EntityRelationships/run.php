---
title: 'Entity relationship extraction'
docname: 'entity_relationships'
---

## Overview

In cases where relationships exist between entities, it's vital to define them
explicitly in the model.

Following example demonstrates how to define relationships between users by
incorporating an `$id` and `$coworkers` fields.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Instructor;

class UserDetail
{
    /** Unique identifier for each user. */
    public int $id;
    public int $age;
    public string $name;
    public string $role;
    /**
     * @var int[] Correct and complete list of coworker IDs, representing
     * collaboration between users.
     */
    public array $coworkers;
}

class UserRelationships
{
    /**
     * @var UserDetail[] Collection of users, correctly capturing the
     * relationships among them.
     */
    public array $users;
}

$text = "Jason is 25 years old. He is a Python programmer of Apex website.\
 Amanda is a contractor working with Jason on Apex website. John is 40yo\
 and he's CEO - Jason reports to him.";

$relationships = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserRelationships::class,
);

dump($relationships);

assert(!empty($relationships->users));
?>
```
