## Changing LLM model and options

You can specify model and other options that will be passed to OpenAI / LLM endpoint.

```php
<?php

$person = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    model: 'gpt-3.5-turbo',
    options: [
        // custom temperature setting
        'temperature' => 0.0
        // ... other options
    ],
);
```

!!! note

    For more details on options available - see [OpenAI PHP client](https://github.com/openai-php/client).


## Providing custom OpenAI client

You can pass a custom configured instance of OpenAI client to the Instructor. This allows you to specify your own API key, organization, base URI, HTTP client, HTTP headers, query parameters, and stream handler.

```php
<?php
use Cognesy\Instructor\Instructor;
use OpenAI\Client;

$yourApiKey = getenv('YOUR_API_KEY');

$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    // default: null
    ->withOrganization('your-organization')
    // default: api.openai.com/v1
    ->withBaseUri('openai.example.com/v1')
    // default: HTTP client found using PSR-18 HTTP Client Discovery
    ->withHttpClient($client = new \GuzzleHttp\Client([]))
    // custom headers
    ->withHttpHeader('X-My-Header', 'foo')
    // ...and query params
    ->withQueryParam('my-param', 'bar')
    // allows to provide a custom stream handler for the http client
    ->withStreamHandler(
        fn (RequestInterface $request): ResponseInterface => $client->send(
            $request,                
            ['stream' => true]
        )
    )
    ->make();

$instructor = new Instructor([Client::class => $client]);

$person = $instructor->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    model: 'gpt-3.5-turbo',
    options: ['temperature' => 0.0],
);
```
