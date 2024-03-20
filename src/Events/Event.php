<?php
namespace Cognesy\Instructor\Events;
use Cognesy\Instructor\Utils\Console;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

abstract class Event
{
    public readonly string $eventId;
    public readonly DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->eventId = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
    }

    public function toLog(): string {
        return $this->logFormat((string) $this);
    }

    public function toConsole(): string {
        $message = (string) $this;
        $message = str_replace("\n", ' ', $message);
        return $this->consoleFormat($message);
    }

    public function print(): void {
        echo $this->toConsole()."\n";
    }

    private function logFormat(string $message): string {
        $class = (new \ReflectionClass($this))->getName();
        return "({$this->eventId}) {$this->createdAt->format('Y-m-d H:i:s v')}ms [$class] - $message";
    }

    private function consoleFormat(string $message = '') : string {
        $segments = explode('\\', (new \ReflectionClass($this))->getName());
        $eventName = array_pop($segments);
        //$eventGroup = implode('\\', $segments);
        return Console::columns([
            [7, '(.'.substr($this->eventId, -4).')'],
            [14, $this->createdAt->format('H:i:s v').'ms'],
            //[15, "{$eventGroup}\\", STR_PAD_LEFT],
            [30, "{$eventName}"],
            '-',
            [-1, $message],
        ], 140);
    }
}
