---
title: 'Using LLM API connections from config file'
docname: 'using_config'
---

## Overview

Instructor allows you to define multiple API connections in `llm.php` file.
This is useful when you want to use different LLMs or API providers in your application.

Connecting to LLM API via predefined connection is as simple as calling `withClient`
method with the connection name.

### Configuration file

Default LLM configuration file is located in `/config/llm.php` in the root directory
of Instructor codebase.

You can set the location of the configuration file via `INSTRUCTOR_CONFIG_PATH` environment
variable. You can use a copy of the default configuration file as a starting point.

LLM config file defines connections to LLM APIs and their parameters. It also specifies
the default connection to be used when calling Instructor without specifying the client
connection.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class User {
    public int $age;
    public string $name;
}

// Get Instructor object with client defined in config.php under 'connections/openai' key
$structuredOutput = (new StructuredOutput)->withConnection('openai');

// Call with custom model and execution mode
$user = $structuredOutput->create(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
)->get();

// Use the results of LLM inference
dump($user);
assert(isset($user->name));
assert(isset($user->age));
?>
```
