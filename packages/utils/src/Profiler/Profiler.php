<?php declare(strict_types=1);

namespace Cognesy\Utils\Profiler;

class Profiler
{
    private array $checkpoints = [];
    static private Profiler $instance;

    /**
     * @phpstan-return static
     */
    static public function get() : static {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        /** @var static */
        return self::$instance;
    }

    static public function mark(string $name, array $context = []): Checkpoint {
        return self::get()->addMark($name, $context);
    }

    static public function delta() : float {
        return self::get()->timeSinceLast();
    }

    static public function summary() : string {
        return self::get()->getSummary();
    }

    public function timeSinceLast() : float {
        $checkpoints = $this->checkpoints;
        $previous = count($checkpoints) - 1;
        $delta = ($previous == -1) ? 0 : (microtime(true) - $checkpoints[$previous]->time);
        return $delta;
    }

    public function addMark(string $name, array $context = []) : Checkpoint {
        $time = microtime(true);
        $previous = count($this->checkpoints) - 1;
        $delta = ($previous == -1) ? 0 : ($time - $this->checkpoints[$previous]->time);
        $trace = debug_backtrace()[1] ?? [];
        $debugTrace = ($trace['class'] ?? 'unknown').'::'.($trace['function'] ?? 'unknown');
        return $this->store($name, $time, $delta, $debugTrace, $context);
    }

    public function getSummary() : string {
        $checkpoints = $this->checkpoints;
        $total = $this->getTotalTime();
        $output = "Total time: $total usec\n";
        foreach ($checkpoints as $checkpoint) {
            $delta = $checkpoint->delta * 1_000_000;
            $delta = number_format($delta, 2);
            $delta = str_pad($delta, 10, ' ', STR_PAD_LEFT);
            $context = $this->renderContext($checkpoint->context);
            $output .= " $delta usec | {$checkpoint->name}{$context} | {$checkpoint->debug}\n";
        }
        return $output;
    }

    public function getFirst() : Checkpoint {
        return reset($this->checkpoints);
    }

    public function getLast() : Checkpoint {
        return end($this->checkpoints);
    }

    public function diff(Checkpoint $a, Checkpoint $b) : float {
        return $b->time - $a->time;
    }

    public function getTotalTime() : float {
        return $this->diff($this->getLast(), $this->getFirst()) * 1_000_000;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function store(
        string $name,
        float $time,
        float $delta,
        string $debug,
        array $context
    ): Checkpoint {
        $checkpoint = new Checkpoint($name, $time, $delta, $debug, $context);
        $this->checkpoints[] = $checkpoint;
        return $checkpoint;
    }

    private function renderContext(array $context) : string {
        if (empty($context)) {
            return '';
        }
        // turn key value pairs into a string, separated by commas
        $context = array_map(fn($key, $value) => "$key=$value", array_keys($context), $context);
        return '('.implode(', ', $context).')';
    }
}
