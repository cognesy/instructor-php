---
title: 'Using LLM API connection presets from config file'
docname: 'using_config'
---

## Overview

Instructor allows you to define multiple API connection presets in `llm.php` file.
This is useful when you want to use different LLMs or API providers in your application.

Connecting to LLM API via predefined connection is as simple as calling `withPreset`
method with the preset name.

### Configuration file

Default LLM configuration file is located in `/config/llm.php` in the root directory
of Instructor codebase.

You can set the location of the configuration file via `INSTRUCTOR_CONFIG_PATH` environment
variable. You can use a copy of the default configuration file as a starting point.

LLM config file defines available connection presets to LLM APIs and their parameters.
It also specifies the default provider and parameters to be used when calling Instructor.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class User {
    public int $age;
    public string $name;
}

// Get Instructor object with client defined in config.php under 'presets/openai' key
$structuredOutput = (new StructuredOutput)->using('openai');

// Call with custom model and execution mode
$user = $structuredOutput->with(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
)->get();

// Use the results of LLM inference
dump($user);
assert(isset($user->name));
assert(isset($user->age));
?>
```
