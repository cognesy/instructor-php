---
title: Connection Issues
description: 'Learn how to troubleshoot connection issues when using Polyglot.'
---

Network connectivity problems can prevent successful API requests.

## Symptoms

- Error messages like "connection timeout," "failed to connect," or "network error"
- Long delays before errors appear

## Solutions

1. **Check Internet Connection**: Ensure your server has a stable internet connection

2. **Verify API Endpoint**: Make sure the API endpoint URL is correct
```php
// In your configuration file (config/llm.php)
return [
    'apiUrl' => 'https://api.openai.com/v1', // Correct URL
];
```

3. **Proxy Settings**: If you're behind a proxy, configure it properly

```php
// Using custom HTTP client with proxy settings
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use GuzzleHttp\Client;

$config = new HttpClientConfig(
    requestTimeout: 30,
    connectTimeout: 10,
    driver: 'guzzle',
);

$httpClient = (new HttpClientBuilder())
    ->withConfig($config)
    ->withClientInstance('guzzle', new Client([
        'proxy' => 'http://proxy.example.com:8080',
    ]))
    ->create();
$inference = Inference::fromRuntime(InferenceRuntime::using(
    preset: 'openai',
    httpClient: $httpClient,
));
```

4. **Firewall Rules**: Check if your firewall is blocking outgoing connections to API endpoints

5. **DNS Resolution**: Ensure your DNS is resolving the API domains correctly
