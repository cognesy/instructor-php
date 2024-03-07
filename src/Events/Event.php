<?php
namespace Cognesy\Instructor\Events;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

abstract class Event
{
    public readonly string $eventId;
    public readonly Carbon $createdAt;

    public function __construct()
    {
        $this->eventId = Uuid::uuid4();
        $this->createdAt = Carbon::now();
    }

    public function __toString(): string
    {
        return $this->short();
    }

    protected function format(string $message): string
    {
        return $this->short($message);
    }

    private function long(string $message): string
    {
        $shortId = substr($this->eventId, -4);
        $class = (new \ReflectionClass($this))->getName();
        $segments = explode('\\', $class);
        $eventName = $segments[count($segments) - 1];
        $eventGroup = $segments[count($segments) - 2];
        $source = "...\\{$eventGroup}\\{$eventName}";
        return "({$shortId}) {$this->createdAt->format('Y-m-d H:i:s v')}ms [$source] : $message";
    }

    private function short(string $message = '') : string {
        $segments = explode('\\', (new \ReflectionClass($this))->getName());
        $eventName = array_pop($segments);
        $eventGroup = implode('\\', $segments);
        return $this->columns([
            [7, '(.'.substr($this->eventId, -4).')'],
            [5, $this->createdAt->format('v').'ms'],
            [15, "{$eventGroup}\\", STR_PAD_LEFT],
            [20, "{$eventName}", STR_PAD_LEFT],
            '-',
            [-1, $message],
        ]);
    }

    private function columns(array $columns): string {
        $terminalWidth = 140;
        $message = '';
        foreach ($columns as $row) {
            if (is_string($row)) {
                $message .= $row;
            } else {
                if ($row[0] == -1) {
                    $row[0] = $terminalWidth - strlen($message);
                }
                $message .= $this->toColumn(
                    chars: $row[0],
                    text: $row[1],
                    align: $row[2]??STR_PAD_RIGHT
                );
            }
            $message .= ' ';
        }
        return trim($message);
    }

    private function toColumn(int $chars, mixed $text, int $align): string {
        $short = ($align == STR_PAD_LEFT)
            ? substr($text, -$chars)
            : substr($text, 0, $chars);
        if ($text != $short) {
            $short = ($align == STR_PAD_LEFT)
                ? '…'.substr($short,1)
                : substr($short, 0, -1).'…';
        }
        return str_pad($short, $chars, ' ', $align);
    }
}
