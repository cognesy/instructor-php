<?php

namespace Cognesy\Http\Tests\Support;

/**
 * HTTP Test Router - Handles routing and responses for integration testing
 * Provides HTTPBin-compatible endpoints for reliable HTTP client testing
 */
class HttpTestRouter
{
    private array $routes = [];

    public function __construct()
    {
        $this->registerRoutes();
    }

    public function handleRequest(string $method, string $path, array $args, array $headers, string $body): void
    {
        // Handle preflight OPTIONS requests
        if ($method === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        // Find matching route
        $handler = $this->findRoute($method, $path);
        
        if ($handler === null) {
            $this->notFound($path);
            return;
        }

        // Execute handler
        $handler($method, $path, $args, $headers, $body);
    }

    private function registerRoutes(): void
    {
        // Health check
        $this->routes['GET']['/health'] = [$this, 'health'];
        
        // Basic HTTP methods
        $this->routes['GET']['/get'] = [$this, 'echoRequest'];
        $this->routes['POST']['/post'] = [$this, 'echoRequest'];
        $this->routes['PUT']['/put'] = [$this, 'echoRequest'];
        $this->routes['DELETE']['/delete'] = [$this, 'echoRequest'];
        
        // Status endpoints
        $this->routes['GET']['#^/status/(\d+)$#'] = [$this, 'status'];
        
        // Delay endpoints
        $this->routes['GET']['#^/delay/(\d+)$#'] = [$this, 'delay'];
        
        // Content type endpoints
        $this->routes['GET']['/json'] = [$this, 'json'];
        $this->routes['GET']['/xml'] = [$this, 'xml'];
        $this->routes['GET']['/html'] = [$this, 'html'];
        
        // Streaming endpoints
        $this->routes['GET']['#^/stream/(\d+)$#'] = [$this, 'stream'];
        $this->routes['GET']['#^/sse/(\d+)$#'] = [$this, 'serverSentEvents'];
        $this->routes['GET']['#^/stream-slow/(\d+)$#'] = [$this, 'streamSlow'];
    }

    private function findRoute(string $method, string $path): ?callable
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        // First try exact match
        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        }

        // Then try regex patterns
        foreach ($this->routes[$method] as $pattern => $handler) {
            if (strpos($pattern, '#') === 0 && preg_match($pattern, $path)) {
                return $handler;
            }
        }

        return null;
    }

    // Route handlers

    private function health(): void
    {
        echo 'OK';
    }

    private function echoRequest(string $method, string $path, array $args, array $headers, string $body): void
    {
        header('Content-Type: application/json');
        
        $response = [
            'args' => $args,
            'headers' => $headers,
            'url' => $this->getCurrentUrl(),
            'origin' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'method' => $method
        ];

        if (!empty($body)) {
            $response['data'] = $body;
            $jsonData = json_decode($body, true);
            if ($jsonData !== null) {
                $response['json'] = $jsonData;
            }
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    private function status(string $method, string $path): void
    {
        if (preg_match('#^/status/(\d+)$#', $path, $matches)) {
            $statusCode = (int)$matches[1];
            $statusCode = min(max($statusCode, 100), 599); // Valid HTTP status range
            
            http_response_code($statusCode);
            header('Content-Type: application/json');
            
            $statusText = $this->getStatusText($statusCode);
            echo json_encode([
                'status' => $statusCode,
                'message' => $statusText,
                'url' => $this->getCurrentUrl()
            ]);
        }
    }

    private function delay(string $method, string $path, array $args, array $headers, string $body): void
    {
        if (preg_match('#^/delay/(\d+)$#', $path, $matches)) {
            $delay = min((int)$matches[1], 10); // Max 10 seconds for safety
            sleep($delay);
            
            header('Content-Type: application/json');
            echo json_encode([
                'delay' => $delay,
                'message' => "Delayed for {$delay} seconds",
                'url' => $this->getCurrentUrl()
            ]);
        }
    }

    private function json(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'slideshow' => [
                'author' => 'Test Author',
                'date' => date('Y-m-d'),
                'slides' => [
                    ['title' => 'Test Slide 1', 'type' => 'all'],
                    ['title' => 'Test Slide 2', 'type' => 'all']
                ],
                'title' => 'Test Slideshow'
            ]
        ]);
    }

    private function xml(): void
    {
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><test><message>XML response</message></test>';
    }

    private function html(): void
    {
        header('Content-Type: text/html');
        echo '<html><body><h1>Test HTML Response</h1></body></html>';
    }

    private function stream(string $method, string $path): void
    {
        if (preg_match('#^/stream/(\d+)$#', $path, $matches)) {
            $lines = min((int)$matches[1], 20); // Max 20 lines for safety
            
            header('Content-Type: application/x-ndjson');
            header('Cache-Control: no-cache');
            
            for ($i = 0; $i < $lines; $i++) {
                echo json_encode([
                    'line' => $i,
                    'data' => "stream data $i",
                    'timestamp' => microtime(true)
                ]) . "\n";
                
                $this->flush();
                usleep(1000); // 1ms delay between lines
            }
        }
    }

    private function serverSentEvents(string $method, string $path): void
    {
        if (preg_match('#^/sse/(\d+)$#', $path, $matches)) {
            $events = min((int)$matches[1], 10); // Max 10 events for safety
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            for ($i = 0; $i < $events; $i++) {
                echo "id: event_$i\n";
                echo "event: message\n";
                echo "data: " . json_encode([
                    'event_id' => $i,
                    'message' => "SSE event $i",
                    'timestamp' => time()
                ]) . "\n";
                echo "\n"; // Required double newline after each event
                
                $this->flush();
                usleep(2000); // 2ms delay between events
            }
        }
    }

    private function streamSlow(string $method, string $path): void
    {
        if (preg_match('#^/stream-slow/(\d+)$#', $path, $matches)) {
            $lines = min((int)$matches[1], 5); // Max 5 lines for timing tests
            
            header('Content-Type: application/x-ndjson');
            header('Cache-Control: no-cache');
            
            for ($i = 0; $i < $lines; $i++) {
                echo json_encode([
                    'line' => $i,
                    'data' => "slow stream data $i",
                    'delay' => 100,
                    'timestamp' => microtime(true)
                ]) . "\n";
                
                $this->flush();
                usleep(100000); // 100ms delay between lines for timing tests
            }
        }
    }

    private function notFound(string $path): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Not found',
            'path' => $path,
            'message' => 'The requested endpoint does not exist'
        ]);
    }

    // Helper methods

    private function getCurrentUrl(): string
    {
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return "$scheme://$host$uri";
    }

    private function getStatusText(int $status): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];

        return $statusTexts[$status] ?? 'Unknown Status';
    }

    private function flush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}