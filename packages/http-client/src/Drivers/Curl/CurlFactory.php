<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;

/**
 * CurlFactory - Configuration Logic
 *
 * Stateless factory that creates and configures curl handles
 * according to HttpRequest and HttpClientConfig specifications.
 */
final class CurlFactory
{
    public function __construct(
        private readonly HttpClientConfig $config,
    ) {}

    public function createHandle(HttpRequest $request): CurlHandle {
        $handle = CurlHandle::create($request->url(), $request->method());

        $this->configureMethod($handle, $request);
        $this->configureHeaders($handle, $request);
        $this->configureBody($handle, $request);
        $this->configureTimeouts($handle);
        $this->configureSsl($handle);
        $this->configureRedirects($handle);
        $this->configureHttpVersion($handle);

        return $handle;
    }

    private function configureMethod(CurlHandle $handle, HttpRequest $request): void {
        $handle->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($request->method()));
    }

    private function configureHeaders(CurlHandle $handle, HttpRequest $request): void {
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = is_array($value)
                ? "{$name}: " . implode(', ', $value)
                : "{$name}: {$value}";
        }
        $handle->setOption(CURLOPT_HTTPHEADER, $headers);
    }

    private function configureBody(CurlHandle $handle, HttpRequest $request): void {
        $body = $request->body()->toString();
        if ($body !== '') {
            $handle->setOption(CURLOPT_POSTFIELDS, $body);
        }
    }

    private function configureTimeouts(CurlHandle $handle): void {
        $handle->setOption(CURLOPT_CONNECTTIMEOUT, $this->config->connectTimeout ?? 3);
        $handle->setOption(CURLOPT_TIMEOUT, $this->config->requestTimeout ?? 30);
    }

    private function configureSsl(CurlHandle $handle): void {
        $handle->setOption(CURLOPT_SSL_VERIFYPEER, $this->config->verifyTls);
        $handle->setOption(CURLOPT_SSL_VERIFYHOST, $this->config->verifyTls ? 2 : 0);
    }

    private function configureRedirects(CurlHandle $handle): void {
        $handle->setOption(CURLOPT_FOLLOWLOCATION, $this->config->followRedirects);
        $handle->setOption(CURLOPT_MAXREDIRS, $this->config->maxRedirects ?? 5);
    }

    private function configureHttpVersion(CurlHandle $handle): void {
        $handle->setOption(CURLOPT_HTTP_VERSION, $this->curlHttpVersion($this->config->httpVersion ?? '2.0'));
    }

    private function curlHttpVersion(string $version): int {
        return match ($version) {
            '1.0' => CURL_HTTP_VERSION_1_0,
            '1.1' => CURL_HTTP_VERSION_1_1,
            '2', '2.0' => CURL_HTTP_VERSION_2_0,
            '3', '3.0' => defined('CURL_HTTP_VERSION_3')
                ? constant('CURL_HTTP_VERSION_3')
                : CURL_HTTP_VERSION_2_0,
            default => CURL_HTTP_VERSION_2_0,
        };
    }
}
