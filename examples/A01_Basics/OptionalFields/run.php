---
title: 'Making some fields optional'
docname: 'optional_fields'
id: 'f3d7'
---
## Overview

Use PHP's nullable types by prefixing type name with question mark (?) to declare
component fields which are optional. Set a default value to prevent undesired
defaults like nulls or empty strings.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class UserDetail
{
    public int $age;
    public string $firstName;
    public ?string $lastName;
}

$user = (new StructuredOutput)
    ->withMessages('Jason is 25 years old.')
    ->withResponseClass(UserDetail::class)
    ->get();

dump($user);

assert(!isset($user->lastName) || $user->lastName === '');
?>
```
