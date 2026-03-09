<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Tests\Support;

final class IntegrationTestServer
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

        self::stop();
        self::$port = self::findAvailablePort();
        self::$baseUrl = 'http://localhost:' . self::$port;

        $command = sprintf('php -S localhost:%d %s 2>/dev/null', self::$port, self::serverScript());
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$process = proc_open($command, $descriptorSpec, $pipes, __DIR__);
        if (!is_resource(self::$process)) {
            throw new \RuntimeException('Failed to start integration test HTTP server');
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        usleep(200000);

        for ($i = 0; $i < 25; $i++) {
            if (self::isServerRunning()) {
                self::$started = true;
                return self::$baseUrl;
            }

            usleep(200000);
        }

        $status = proc_get_status(self::$process);
        self::stop();

        throw new \RuntimeException(
            'Integration test HTTP server failed to start on port ' . self::$port
            . '. Process running: ' . ($status['running'] ? 'yes' : 'no')
            . '. Exit code: ' . ($status['exitcode'] ?? 'unknown')
        );
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            $status = proc_get_status(self::$process);

            if ($status['running']) {
                proc_terminate(self::$process, 15);

                for ($i = 0; $i < 10; $i++) {
                    usleep(100000);
                    $status = proc_get_status(self::$process);
                    if (!$status['running']) {
                        break;
                    }
                }

                if ($status['running']) {
                    proc_terminate(self::$process, 9);
                    usleep(100000);
                }
            }

            proc_close(self::$process);
            self::$process = null;
        }

        self::$started = false;
        self::$port = 0;
        self::$baseUrl = '';
    }

    private static function isServerRunning(): bool
    {
        if (self::$baseUrl === '' || self::$port === 0) {
            return false;
        }

        $connection = @fsockopen('localhost', self::$port, $errno, $errstr, 1);
        if (!$connection) {
            return false;
        }

        fclose($connection);

        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET',
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents(self::$baseUrl . '/health', false, $context);

        return $result !== false && trim($result) === 'OK';
    }

    private static function findAvailablePort(int $startPort = 8950): int
    {
        $basePort = $startPort + (getmypid() % 50);

        for ($i = 0; $i < 10; $i++) {
            $port = $basePort + $i;
            $errno = $errstr = null;
            set_error_handler(static function () {});
            $connection = fsockopen('localhost', $port, $errno, $errstr, 0.1);
            restore_error_handler();

            if (!$connection) {
                return $port;
            }

            fclose($connection);
        }

        return random_int(9000, 9999);
    }

    private static function serverScript(): string
    {
        return __DIR__ . '/HttpTestServer.php';
    }
}
