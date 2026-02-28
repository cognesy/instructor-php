---
title: 'Modifying Settings Path'
docname: 'settings'
---

## Overview

This example demonstrates how to modify the settings path for the Instructor library.
This is useful when you want to use a custom configuration directory instead of the default one.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Settings;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;

class UserDetail
{
    public int $age;
    public string $firstName;
    public ?string $lastName;
}

// set the configuration path to custom directory
Settings::setPath(__DIR__ . '/config');

$debugHttpClient = (new HttpClientBuilder)->withHttpDebugPreset('on')->create();

$user = (new StructuredOutput(
    StructuredOutputRuntime::fromDefaults(httpClient: $debugHttpClient)
))
    ->withMessages('Jason is 25 years old.')
    ->withResponseClass(UserDetail::class)
    ->get();

dump($user);

assert(!isset($user->lastName) || $user->lastName === '');
?>
```
