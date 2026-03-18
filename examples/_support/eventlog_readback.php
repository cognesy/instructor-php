<?php declare(strict_types=1);

final class ExampleEventLog
{
    public static function path(string $prefix): string
    {
        return sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4)) . '.jsonl';
    }

    /** @return list<array<string, mixed>> */
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $entries = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }
        return $entries;
    }

    /** @param list<array<string, mixed>> $entries */
    public static function print(array $entries, int $limit = 8): void
    {
        foreach (array_slice($entries, 0, $limit) as $i => $entry) {
            echo sprintf(
                "%d. [%s] %s %s %s\n",
                $i + 1,
                $entry['timestamp'] ?? 'n/a',
                strtoupper((string) ($entry['level'] ?? 'n/a')),
                $entry['channel'] ?? 'n/a',
                $entry['message'] ?? 'n/a',
            );

            $context     = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            $correlation = is_array($context['correlation'] ?? null) ? $context['correlation'] : [];
            if ($correlation !== []) {
                echo '   correlation: ' . json_encode($correlation, JSON_UNESCAPED_SLASHES) . "\n";
            }

            $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
            if ($payload !== []) {
                echo '   payload keys: ' . implode(', ', array_slice(array_keys($payload), 0, 8)) . "\n";
            }
        }
    }
}
