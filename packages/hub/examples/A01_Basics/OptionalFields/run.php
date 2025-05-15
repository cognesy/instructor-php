---
title: 'Making some fields optional'
docname: 'optional_fields'
---

## Overview

Use PHP's nullable types by prefixing type name with question mark (?) to mark
component fields which are optional and set a default value to prevent undesired
defaults like empty strings.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class UserRole
{
    public string $title;
}

class UserDetail
{
    public int $age;
    public string $name;
    public ?UserRole $role;
}

$user = (new StructuredOutput)->create(
    messages: [["role" => "user",  "content" => "Jason is 25 years old."]],
    responseModel: UserDetail::class,
)->get();


dump($user);

assert(!isset($user->role));
?>
```
