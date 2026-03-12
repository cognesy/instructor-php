# Troubleshooting

Common issues and their solutions when working with Instructor for Laravel.

## Installation Issues

### Package Not Found

**Error:**
```
Package cognesy/instructor-laravel not found
// @doctest id="7d94"
```

**Solution:**
Ensure you have the correct package name and your Composer repository cache is up to date:

```bash
composer clear-cache
composer require cognesy/instructor-laravel
# @doctest id="f2fc"
```

If you are using a private Packagist mirror, verify the package is available in your configured repositories.

### Service Provider Not Registered

**Error:**
```
Class 'Cognesy\Instructor\Laravel\Facades\StructuredOutput' not found
// @doctest id="e4f3"
```

**Solution:**
If auto-discovery is disabled in your `composer.json`, manually register the provider:

```php
// config/app.php (Laravel 10)
'providers' => [
    Cognesy\Instructor\Laravel\InstructorServiceProvider::class,
],
// @doctest id="d2b6"
```

If auto-discovery is enabled but the provider is not loading, clear the cached package manifest:

```bash
php artisan package:discover
php artisan config:clear
php artisan cache:clear
# @doctest id="242b"
```

---

## API Key Issues

### API Key Not Configured

**Error:**
```
No API key configured for connection 'openai'
// @doctest id="9cee"
```

**Solution:**
Add your API key to `.env`:

```env
OPENAI_API_KEY=sk-your-key-here
// @doctest id="f0db"
```

Then clear the config cache so Laravel picks up the change:

```bash
php artisan config:clear
# @doctest id="e277"
```

### Invalid API Key

**Error:**
```
401 Unauthorized: Invalid API key
// @doctest id="bb66"
```

**Solution:**
1. Verify your API key is correct by checking the provider's dashboard
2. Check that the key has not expired or been revoked
3. Ensure the key has the required permissions (some providers require specific scopes)
4. Verify there are no extra spaces, newlines, or quotes around the key in `.env`
5. Run `php artisan instructor:test` to confirm the key works

### Rate Limiting

**Error:**
```
429 Too Many Requests
// @doctest id="0fda"
```

**Solution:**
Rate limiting occurs when you exceed the provider's API call limits. Strategies to mitigate this:

1. Implement rate limiting in your application using Laravel's `RateLimiter`
2. Upgrade your API plan for higher limits
3. Add response caching to reduce redundant API calls
4. Spread requests across multiple providers using the `connection()` method

```php
use Illuminate\Support\Facades\RateLimiter;

if (RateLimiter::tooManyAttempts('llm-calls', 60)) {
    throw new TooManyRequestsException();
}

RateLimiter::hit('llm-calls');
// @doctest id="0fc0"
```

---

## Extraction Issues

### Response Does Not Match Model

**Error:**
```
Failed to deserialize response to PersonData
// @doctest id="76cd"
```

**Solution:**
This usually means the LLM produced JSON that does not conform to your response model's structure. Improve the extraction by:

1. Adding more descriptive property comments (these become schema descriptions)
2. Providing few-shot examples
3. Increasing max retries so the model gets another chance

```php
final class PersonData
{
    public function __construct(
        /** The person's full legal name (first and last) */
        public readonly string $name,

        /** The person's age as a whole number */
        public readonly int $age,
    ) {}
}

$result = StructuredOutput::with(
    messages: $text,
    responseModel: PersonData::class,
    maxRetries: 5,  // Increase retries
    examples: [...], // Add examples
)->get();
// @doctest id="31a0"
```

### Validation Failures

**Error:**
```
Validation failed after 3 retries
// @doctest id="38ae"
```

**Solution:**
The LLM repeatedly produced output that did not pass your validation constraints. Check whether:

1. Your validation constraints are not too strict for the input data
2. The retry prompt gives the LLM enough context to understand the errors
3. The max retries count is sufficient

```php
$result = StructuredOutput::with(
    messages: $text,
    responseModel: MyModel::class,
    maxRetries: 5,
    retryPrompt: 'Previous response failed: {errors}. Please fix these specific issues.',
)->get();
// @doctest id="0f80"
```

Review your application logs to see the exact validation errors from each retry attempt.

### Null Values for Required Fields

**Problem:** The LLM returns `null` for fields you expected to have values.

**Solution:**
This happens when the input text does not contain enough information for the LLM to populate a field. Strategies:

1. Make the input text clearer or more detailed
2. Add better property descriptions that explain what to look for
3. Use a system prompt that instructs the model to infer values from context
4. Mark fields as nullable if they are truly optional

```php
$result = StructuredOutput::with(
    messages: $text,
    responseModel: MyModel::class,
    system: 'Extract all available information. If a field is not found in the text, make a reasonable inference based on context.',
)->get();
// @doctest id="c8c6"
```

---

## Timeout Issues

### Request Timeout

**Error:**
```
cURL error 28: Operation timed out
// @doctest id="f596"
```

**Solution:**
The API call took longer than the configured timeout. This is common with large inputs, complex response models, or heavily loaded provider APIs.

Increase the timeout in configuration:

```php
// config/instructor.php
'http' => [
    'timeout' => 300, // 5 minutes
    'connect_timeout' => 60,
],
// @doctest id="dc82"
```

Or override per-request using options:

```php
$result = StructuredOutput::withOptions([
    'timeout' => 300,
])->with(...)->get();
// @doctest id="7f25"
```

### Streaming Timeout

**Problem:** Streaming requests timeout before the LLM finishes generating.

**Solution:**
For long-running streaming responses, ensure both the HTTP timeout and PHP's execution time limit are sufficient:

```php
set_time_limit(0); // Disable PHP timeout for this request

$stream = StructuredOutput::with(...)
    ->withStreaming()
    ->stream();
// @doctest id="40ab"
```

In production, consider running streaming extractions in a queue worker where time limits are typically more generous.

---

## Testing Issues

### Fake Not Working

**Problem:** Real API calls are made despite using `fake()`.

**Solution:**
Ensure you call `fake()` **before** any code that triggers an extraction. The fake replaces the facade's bound instance, and calls made before the swap reach the real service.

```php
// CORRECT
$fake = StructuredOutput::fake([...]);
$result = $myService->extract(); // Uses fake

// WRONG
$result = $myService->extract(); // Real API call!
$fake = StructuredOutput::fake([...]); // Too late
// @doctest id="ea75"
```

### Http::fake() Not Mocking

**Problem:** `Http::fake()` does not affect Instructor calls.

**Solution:**
Ensure the HTTP driver is set to `'laravel'` in your configuration. If a different driver is configured, the package will not route requests through Laravel's HTTP client.

```php
// config/instructor.php
'http' => [
    'driver' => 'laravel',
],
// @doctest id="4941"
```

Also verify that your test environment is not overriding this setting via an environment variable.

---

## Performance Issues

### Slow Responses

**Solutions:**
1. **Use a faster model** -- `gpt-4o-mini` is significantly faster than `gpt-4o` for simple extractions
2. **Use a fast-inference provider** -- Groq offers very low latency for supported models
3. **Enable response caching** -- avoid redundant calls for identical inputs
4. **Reduce input size** -- truncate long inputs to the minimum necessary context

```php
// Use faster provider
$result = StructuredOutput::connection('groq')
    ->with(...)->get();

// Cache responses
$result = Cache::remember($cacheKey, 3600, fn () =>
    StructuredOutput::with(...)->get()
);
// @doctest id="6ae5"
```

### High Token Usage

**Solutions:**
1. Use concise system prompts -- every token in the prompt counts toward your bill
2. Truncate long inputs to the essential content
3. Use smaller response models with fewer properties
4. Choose a model with a lower per-token cost

```php
// Truncate long text
$text = Str::limit($longText, 8000);

$result = StructuredOutput::with(
    messages: $text,
    responseModel: MyModel::class,
    system: 'Extract data. Be concise.', // Short prompt
)->get();
// @doctest id="0bac"
```

---

## Memory Issues

### Out of Memory

**Error:**
```
Allowed memory size exhausted
// @doctest id="615d"
```

**Solution:**
This can happen when processing many documents in a single request. Strategies:

1. Process documents in chunks and allow garbage collection between batches
2. Use streaming for large responses
3. Dispatch extraction jobs to a queue worker with higher memory limits

```php
// Process in chunks
$documents->chunk(10)->each(function ($chunk) {
    foreach ($chunk as $doc) {
        $result = StructuredOutput::with(...)
            ->get();
        // Process result immediately
    }
    gc_collect_cycles();
});
// @doctest id="c1bd"
```

---

## Common Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| `Connection refused` | API endpoint unreachable | Check network, firewall, and API URL |
| `Invalid JSON` | LLM returned malformed JSON | Increase retries, simplify response model |
| `Model not found` | Wrong model name | Check model name spelling in config |
| `Quota exceeded` | API billing limit reached | Upgrade plan or wait for reset |
| `Context length exceeded` | Input + output exceeds model limit | Truncate input or use a model with larger context |
| `Invalid request` | Malformed API request | Check request parameters and model compatibility |

---

## Getting Help

If you are still stuck after trying the solutions above:

1. **Check the logs** for detailed error information:
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Enable debug logging** for maximum visibility into what is happening:
   ```php
   // config/instructor.php
   'logging' => [
       'enabled' => true,
       'level' => 'debug',
       'preset' => 'default',
   ],
   ```

3. **Test the API directly** to isolate whether the issue is in your configuration or your code:
   ```bash
   php artisan instructor:test --connection=openai
   php artisan instructor:test --connection=anthropic --inference
   ```

4. **Search existing issues** on GitHub:
   https://github.com/cognesy/instructor-php/issues

5. **Open a new issue** with:
   - PHP version (`php -v`)
   - Laravel version (`php artisan --version`)
   - Package version (`composer show cognesy/instructor-laravel`)
   - Full error message and stack trace
   - Minimal reproduction code
