<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Tests\Support;

/** Local HTTP server owned by the Laravel integration test suite. */
final class IntegrationTestServer
{
    private static mixed $process = null;
    private static int $port = 0;
    private static string $baseUrl = '';

    public static function start(): string
    {
        if (self::isRunning()) {
            return self::$baseUrl;
        }

        self::stop();
        self::$port = self::availablePort();
        self::$baseUrl = sprintf('http://127.0.0.1:%d', self::$port);
        self::$process = proc_open(
            [PHP_BINARY, '-S', sprintf('127.0.0.1:%d', self::$port), __DIR__ . '/HttpTestServer.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            __DIR__,
        );

        if (!is_resource(self::$process)) {
            throw new \RuntimeException('Failed to start the Laravel test server');
        }

        array_walk($pipes, static fn($pipe) => fclose($pipe));

        for ($attempt = 0; $attempt < 25; $attempt++) {
            if (self::responds()) {
                return self::$baseUrl;
            }
            usleep(200_000);
        }

        $port = self::$port;
        $status = proc_get_status(self::$process);
        self::stop();

        throw new \RuntimeException(sprintf(
            'Laravel test server failed to start on port %d. Process running: %s. Exit code: %d',
            $port,
            $status['running'] ? 'yes' : 'no',
            $status['exitcode'],
        ));
    }

    public static function stop(): void
    {
        if (!is_resource(self::$process)) {
            self::reset();
            return;
        }

        proc_terminate(self::$process);
        proc_close(self::$process);
        self::reset();
    }

    private static function isRunning(): bool
    {
        return is_resource(self::$process) && self::responds();
    }

    private static function responds(): bool
    {
        if (self::$baseUrl === '') {
            return false;
        }

        set_error_handler(static fn(): bool => true);
        $connection = fsockopen('127.0.0.1', self::$port, $errorCode, $errorMessage, 1);
        restore_error_handler();
        if (!is_resource($connection)) {
            return false;
        }
        fclose($connection);

        $context = stream_context_create(['http' => ['timeout' => 1]]);
        set_error_handler(static fn(): bool => true);
        $response = file_get_contents(self::$baseUrl . '/health', false, $context);
        restore_error_handler();

        return $response === 'OK';
    }

    private static function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new \RuntimeException(sprintf('Failed to reserve test server port: %s', $errorMessage));
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($address)) {
            throw new \RuntimeException('Failed to determine test server port');
        }

        return (int) substr($address, (int) strrpos($address, ':') + 1);
    }

    private static function reset(): void
    {
        self::$process = null;
        self::$port = 0;
        self::$baseUrl = '';
    }
}
