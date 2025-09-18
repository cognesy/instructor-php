<?php

namespace Cognesy\Http\Tests\Support;

/**
 * Local HTTP test server for integration tests
 * Provides fast, reliable HTTPBin-like endpoints for real HTTP testing
 */
class IntegrationTestServer
{
    private static $process = null;
    private static int $port = 0;
    private static string $baseUrl = '';
    private static bool $started = false;
    
    public static function start(): string
    {
        if (self::$started && self::isServerRunning()) {
            return self::$baseUrl;
        }
        
        self::stop(); // Clean up any existing process
        
        // Find available port
        self::$port = self::findAvailablePort();
        self::$baseUrl = 'http://localhost:' . self::$port;
        
        // Create server script
        $serverScript = self::createServerScript();
        
        // Start PHP built-in server with output suppression
        $command = sprintf('php -S localhost:%d %s 2>/dev/null', self::$port, $serverScript);
        
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout 
            2 => ['pipe', 'w'],  // stderr
        ];
        
        self::$process = proc_open($command, $descriptorSpec, $pipes, __DIR__);
        
        if (!is_resource(self::$process)) {
            throw new \RuntimeException('Failed to start integration test HTTP server');
        }
        
        // Close pipes to prevent hanging
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        
        // Give PHP server more time to start and bind to port
        usleep(200000); // 200ms initial wait
        
        // Wait for server to start with timeout and longer intervals
        $maxRetries = 25; // 5 seconds max (25 * 200ms)
        for ($i = 0; $i < $maxRetries; $i++) {
            if (self::isServerRunning()) {
                self::$started = true;
                return self::$baseUrl;
            }
            usleep(200000); // 200ms between checks
        }
        
        // Get process status for debugging
        $status = proc_get_status(self::$process);
        self::stop();
        
        throw new \RuntimeException(
            "Integration test HTTP server failed to start on port " . self::$port . 
            ". Process running: " . ($status['running'] ? 'yes' : 'no') .
            ". Exit code: " . ($status['exitcode'] ?? 'unknown')
        );
    }
    
    public static function stop(): void
    {
        if (self::$process !== null) {
            $status = proc_get_status(self::$process);
            if ($status['running']) {
                // Try graceful termination first
                proc_terminate(self::$process, 15); // SIGTERM
                
                // Wait for graceful shutdown
                for ($i = 0; $i < 10; $i++) {
                    usleep(100000); // 100ms
                    $status = proc_get_status(self::$process);
                    if (!$status['running']) {
                        break;
                    }
                }
                
                // Force kill if still running
                if ($status['running']) {
                    proc_terminate(self::$process, 9); // SIGKILL
                    usleep(100000); // Give it time to die
                }
            }
            proc_close(self::$process);
            self::$process = null;
        }
        
        // Note: HttpTestServer.php is a permanent file, no cleanup needed
        
        // Reset state
        self::$started = false;
        self::$port = 0;
        self::$baseUrl = '';
    }
    
    public static function getBaseUrl(): string
    {
        return self::$baseUrl;
    }
    
    public static function isRunning(): bool
    {
        return self::$started && self::isServerRunning();
    }
    
    private static function isServerRunning(): bool
    {
        if (empty(self::$baseUrl) || self::$port === 0) {
            return false;
        }
        
        // First check if the port is responding at all
        $connection = @fsockopen('localhost', self::$port, $errno, $errstr, 1);
        if (!$connection) {
            return false;
        }
        fclose($connection);
        
        // Then check if it's actually our HTTP server
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET',
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents(self::$baseUrl . '/health', false, $context);
        return $result !== false && trim($result) === 'OK';
    }
    
    private static function findAvailablePort(int $startPort = 8950): int
    {
        // Use a simple deterministic port based on process ID to avoid conflicts
        $basePort = $startPort + (getmypid() % 50);
        
        // Try a few ports starting from our base
        for ($i = 0; $i < 10; $i++) {
            $port = $basePort + $i;
            
            // Simple check without generating warnings
            $errno = $errstr = null;
            set_error_handler(function() {}); // Temporary error handler
            $connection = fsockopen('localhost', $port, $errno, $errstr, 0.1);
            restore_error_handler(); // Restore original error handler
            
            if (!$connection) {
                return $port; // Port is available
            } else {
                fclose($connection);
            }
        }
        
        // Fallback to a random high port
        return rand(9000, 9999);
    }
    
    private static function createServerScript(): string
    {
        // Use the separate HttpTestServer.php file
        return __DIR__ . '/HttpTestServer.php';
    }
}