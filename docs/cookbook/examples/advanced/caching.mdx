---
title: 'Caching'
docname: 'caching'
---

## Overview

> This feature is experimental.

You can enable/disable caching for your requests with `withCache()` method of
`Instructor` class.

When caching is enabled, Instructor will store the response in cache and return
it on subsequent requests with the same parameters (for given API client).

This option is available for all clients. By default, caching is turned off.

NOTE: Currently, Instructor does not support caching for streamed responses.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;
use Cognesy\Instructor\Utils\Profiler\Profiler;

class User {
    public int $age;
    public string $name;
}

// OpenAI auth params
$yourApiKey = Env::get('OPENAI_API_KEY'); // use your own API key

// Create instance of OpenAI client
$client = (new OpenAIClient($yourApiKey));

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

Profiler::mark('start');

$user = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
)->get();

$delta = Profiler::mark('no cache')->mili();
echo "Time elapsed (no cache, default): $delta msec\n\n";

$user2 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
)->withRequestCache()->get();

$delta = Profiler::mark('cache 1st call')->mili();
echo "Time elapsed (cache on, 1st call): $delta msec\n\n";

$user3 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
)->withRequestCache()->get();

$delta = Profiler::mark('cache 2nd call')->mili();
echo "Time elapsed (cache on, 2nd call): $delta msec\n\n";

$user4 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
)->withRequestCache(false)->get();

$delta = Profiler::mark('cache 3rd call')->mili();
echo "Time elapsed (cache turned off again): $delta msec\n\n";

?>
```
