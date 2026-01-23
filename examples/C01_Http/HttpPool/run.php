---
title: 'HTTP Client – Pool Basics'
docname: 'http_client_pool_basics'
---

## Overview

Demonstrates executing multiple HTTP requests in parallel using the pool feature. Shows
how to handle both successful and failed responses from the pool results.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Collections\HttpRequestList;

// Pool basics: execute multiple requests in parallel using the configured driver.
// Note: This example uses real HTTP clients under the hood (default preset),
// so it requires network access to actually run.

$client = HttpClient::default();

$requests = HttpRequestList::of(
    new HttpRequest(
        url: 'https://example.com',
        method: 'GET',
        headers: ['User-Agent' => 'InstructorPHP/1.0'],
        body: '',
        options: [],
    ),
    new HttpRequest(
        url: 'https://www.iana.org/domains/reserved',
        method: 'GET',
        headers: ['User-Agent' => 'InstructorPHP/1.0'],
        body: '',
        options: [],
    ),
    new HttpRequest(
        url: 'https://example.com',
        method: 'GET',
        headers: ['User-Agent' => 'InstructorPHP/1.0'],
        body: '',
        options: [],
    ),
);

// Run pool with a concurrency limit (e.g., 2)
$results = $client->pool($requests, maxConcurrent: 2);

echo "Total: " . count($results) . "\n";
echo "Successes: " . $results->successCount() . ", Failures: " . $results->failureCount() . "\n";

foreach ($results as $i => $result) {
    if ($result->isSuccess()) {
        $resp = $result->unwrap();
        echo sprintf("[%d] %d bytes, status %d\n", $i, strlen($resp->body()), $resp->statusCode());
    } else {
        echo sprintf("[%d] ERROR: %s\n", $i, (string)$result->error());
    }
}
?>
```
