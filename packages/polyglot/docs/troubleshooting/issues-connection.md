---
title: Connection Issues
description: Diagnose and resolve network connectivity problems.
---

Network connectivity problems prevent Polyglot from reaching the provider API. These issues exist below the request layer and are typically caused by incorrect URLs, firewall rules, proxy misconfiguration, or DNS failures.

## Symptoms

- Error messages like "connection timeout," "failed to connect," or "network error"
- Long delays before errors appear
- `TimeoutException` or `NetworkException` from the HTTP client
- Requests that work locally but fail in production (or vice versa)

## Verify the API URL and Endpoint

The most common connection problem is an incorrect `apiUrl` or `endpoint` in your preset. Double-check both values against the provider's documentation:

```yaml
# config/llm/presets/openai.yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
endpoint: /chat/completions
```

Common mistakes include trailing slashes on `apiUrl`, missing the version prefix (e.g. `/v1`), or using an endpoint path that does not match the driver.

## Check Outbound Network Access

Verify that your application environment can reach the provider's API:

```bash
# Test connectivity to OpenAI
curl -s -o /dev/null -w "%{http_code}" https://api.openai.com/v1/models \
  -H "Authorization: Bearer $OPENAI_API_KEY"

# Test connectivity to Anthropic
curl -s -o /dev/null -w "%{http_code}" https://api.anthropic.com/v1/messages \
  -H "x-api-key: $ANTHROPIC_API_KEY" \
  -H "anthropic-version: 2023-06-01"
```

If the `curl` command fails, the problem is at the network layer, not in Polyglot.

## Configure Timeouts

Polyglot uses the `HttpClientConfig` to control connection and request timeouts. The defaults are 3 seconds for connection and 30 seconds for the request. For slow networks or large requests, increase these values:

```php
<?php

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$httpClient = HttpClient::fromConfig(new HttpClientConfig(
    connectTimeout: 10,   // 10 seconds to establish the connection
    requestTimeout: 120,  // 2 minutes for the entire request
));

$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('openai'),
    httpClient: $httpClient,
);

$text = Inference::fromRuntime($runtime)
    ->withMessages('Summarize quantum computing in 200 words.')
    ->get();
```

## Proxy Configuration

If your application runs behind an HTTP proxy, configure the proxy at the HTTP client level. Polyglot itself does not handle proxy settings -- it delegates all transport concerns to the HTTP client.

The approach depends on your HTTP client driver. For the default cURL driver, you can set proxy options through the system environment:

```bash
export HTTP_PROXY=http://proxy.example.com:8080
export HTTPS_PROXY=http://proxy.example.com:8080
```

Alternatively, configure a custom HTTP client with explicit proxy settings for your chosen driver.

## Firewall and Security Groups

In cloud environments, ensure that your security groups or network ACLs allow outbound HTTPS (port 443) to the provider's domain. Common provider domains to allow:

- `api.openai.com`
- `api.anthropic.com`
- `api.mistral.ai`
- `generativelanguage.googleapis.com` (Gemini)
- `localhost:11434` (Ollama, local only)

## DNS Resolution

If DNS is not resolving the provider's domain, you will see connection failures even though the network path is clear. Test DNS resolution independently:

```bash
nslookup api.openai.com
dig api.anthropic.com
```

In containerized environments, check that the container's DNS resolver is configured correctly (e.g. `/etc/resolv.conf`).

## Local Providers (Ollama)

For local providers like Ollama, confirm that the service is running and listening on the expected address:

```bash
# Check if Ollama is running
curl http://localhost:11434/api/version
```

If Ollama is running on a different host or port, update the `apiUrl` in your preset:

```yaml
driver: ollama
apiUrl: 'http://192.168.1.100:11434/v1'
endpoint: /chat/completions
model: 'llama3'
```

## Retry Transient Failures

Network issues are often transient. Use `InferenceRetryPolicy` to automatically retry on connection failures:

```php
<?php

use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withRetryPolicy(new InferenceRetryPolicy(
        maxAttempts: 3,
        baseDelayMs: 500,
        maxDelayMs: 5000,
        jitter: 'full',
    ))
    ->withMessages('Hello')
    ->get();
```

The retry policy automatically retries on `TimeoutException` and `NetworkException` by default.
