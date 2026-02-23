<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

final class JsonLinesBuffer
{
    private string $tail = '';

    /**
     * @return list<string>
     */
    public function consume(string $chunk): array
    {
        $payload = $this->tail . $chunk;
        $lines = preg_split('/\r\n|\r|\n/', $payload);
        if (!is_array($lines)) {
            $this->tail = $payload;
            return [];
        }

        $tail = array_pop($lines);
        $this->tail = is_string($tail) ? $tail : '';

        $ready = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $ready[] = $trimmed;
        }

        return $ready;
    }

    /**
     * @return list<string>
     */
    public function flush(): array
    {
        $last = trim($this->tail);
        $this->tail = '';

        return $last === '' ? [] : [$last];
    }
}
