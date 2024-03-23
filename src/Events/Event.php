<?php
namespace Cognesy\Instructor\Events;
use Cognesy\Instructor\Utils\Console;
use Cognesy\InstructorHub\Utils\Color;
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

    public function asLog(): string {
        return $this->logFormat((string) $this);
    }

    public function asConsole(bool $quote = false): string {
        $message = (string) $this;
        $message = str_replace("\n", ' ', $message);
        return $this->consoleFormat($message, $quote);
    }

    public function print(bool $quote = false): void {
        echo $this->asConsole($quote)."\n";
    }

    /// PRIVATE ////////////////////////////////////////////////////////////////////////////////////////////////////

    private function logFormat(string $message): string {
        $class = (new \ReflectionClass($this))->getName();
        return "({$this->eventId}) {$this->createdAt->format('Y-m-d H:i:s v')}ms [$class] - $message";
    }

    private function consoleFormat(string $message = '', bool $quote = false) : string {
        $segments = explode('\\', (new \ReflectionClass($this))->getName());
        $eventName = array_pop($segments);
        //$eventGroup = implode('\\', $segments);
        if ($quote) {
            $message = Color::DARK_GRAY."`".Color::RESET.$message.Color::DARK_GRAY."`".Color::RESET;
        }
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
