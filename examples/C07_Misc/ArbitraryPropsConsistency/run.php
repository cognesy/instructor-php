---
title: 'Consistent values of arbitrary properties'
docname: 'arbitrary_properties_consistent'
---

## Overview

For multiple records containing arbitrary properties, instruct LLM to get more
consistent key names when extracting properties.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class UserDetail
{
    public int $id;
    public string $key;
    public string $value;
}

class UserDetails
{
    /**
     * @var UserDetail[] Extract information for multiple users.
     * Use consistent key names for properties across users.
     */
    public array $users = [];
}

$text = "Jason is 25 years old. He is a Python programmer.\
 Amanda is UX designer.\
 John is 40yo and he's CEO.";

$list = (new StructuredOutput)->create(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetails::class,
)->get();

dump($list);

assert(!empty($list->users));
?>
```

