<?php
namespace Cognesy\Instructor\Events;
use Cognesy\Instructor\Utils\Console;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Uuid;
use Cognesy\InstructorHub\Utils\Color;
use DateTimeImmutable;

class Event
{
    public readonly string $eventId;
    public readonly DateTimeImmutable $createdAt;
    public mixed $data;

    public function __construct(mixed $data = []) {
        $this->eventId = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->data = $data;
    }

    public function name() : string {
        return (new \ReflectionClass($this))->getShortName();
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

    public function printLog(): void {
        echo $this->asLog()."\n";
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

    public function __toString(): string {
        return Json::encode($this->data, JSON_PRETTY_PRINT);
    }

    protected function dumpVar(mixed $var) : array {
        if (is_scalar($var)) {
            return [$var];
        }
        if (is_array($var)) {
            return $var;
        }
        $reflection = new \ReflectionObject($var);
        $properties = array();
        foreach($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            if (!$property->isInitialized($var)) {
                $properties[$property->getName()] = null;
            } else {
                $properties[$property->getName()] = $property->getValue($var);
            }
        }
        return $properties;
    }
}
