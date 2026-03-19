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

        while (true) {
            $result = $probe();
            if ($result !== null) {
                return $result;
            }

            if (microtime(true) >= $deadline) {
                return null;
            }

            sleep($retryDelaySeconds);
        }
    }
}
