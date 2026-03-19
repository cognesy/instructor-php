<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

final class Polling
{
    /**
     * @template T
     * @param \Closure():T|null $probe
     * @return T|null
     */
    public static function eventually(
        \Closure $probe,
        int $timeoutSeconds = 30,
        int $initialDelaySeconds = 12,
        int $retryDelaySeconds = 6,
    ): mixed {
        sleep($initialDelaySeconds);
        $deadline = microtime(true) + max(0, $timeoutSeconds - $initialDelaySeconds);
        $lastError = null;

        while (true) {
            try {
                $result = $probe();
            } catch (\Throwable $error) {
                $lastError = $error;
                $result = null;
            }

            if ($result !== null) {
                return $result;
            }

            if (microtime(true) >= $deadline) {
                if ($lastError !== null) {
                    throw $lastError;
                }

                return null;
            }

            sleep($retryDelaySeconds);
        }
    }
}
