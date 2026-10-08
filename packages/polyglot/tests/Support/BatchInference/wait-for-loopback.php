<?php

declare(strict_types=1);

function waitForBatchLoopback(int $port): void
{
    $deadline = microtime(true) + 10.0;
    do {
        set_error_handler(static fn (): bool => true);
        try {
            $connection = stream_socket_client("tcp://127.0.0.1:{$port}", $code, $message, 0.1);
        } finally {
            restore_error_handler();
        }
        if ($connection !== false) {
            fclose($connection);
            return;
        }
        usleep(25000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("Batch loopback server did not start on port {$port}: {$message}");
}
