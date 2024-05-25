<?php

namespace Cognesy\Instructor\Utils;

class Profiler
{
    static private array $checkpoints = [];

    static public function mark(string $name, array $context = []): void {
        $time = microtime(true);
        $previous = count(self::$checkpoints) - 1;
        $delta = ($previous == -1) ? 0 : ($time - self::$checkpoints[$previous]['time']);
        $debugTrace = debug_backtrace()[1]['class'].'::'.debug_backtrace()[1]['function'];
        self::store($name, $time, $delta, $debugTrace, $context);
    }

    static private function store(string $name, float $checkpoint, float $delta, string $debug, array $context): void {
        self::$checkpoints[] = [
            'name' => $name,
            'time' => $checkpoint,
            'delta' => $delta,
            'debug' => $debug,
            'context' => $context,
        ];
    }

    static public function getTotalTime() : float {
        $checkpoints = self::$checkpoints;
        return (end($checkpoints)['time'] - reset($checkpoints)['time']) * 1_000_000;
    }

    static public function dump() : void {
        $checkpoints = self::$checkpoints;
        $total = self::getTotalTime();
        $output = "Total time: $total usec\n";
        foreach ($checkpoints as $checkpoint) {
            $delta = $checkpoint['delta'] * 1_000_000;
            // format $delta - remove fractional part
            $delta = number_format($delta, 2);
            // add spaces to align deltas
            $delta = str_pad($delta, 10, ' ', STR_PAD_LEFT);
            $context = self::renderContext($checkpoint['context']);
            $output .= " $delta usec | {$checkpoint['name']}{$context} | {$checkpoint['debug']}\n";
        }
        print $output;
    }

    static private function renderContext(array $context) : string {
        if (empty($context)) {
            return '';
        }
        // turn key value pairs into a string, separated by commas
        $context = array_map(fn($key, $value) => "$key=$value", array_keys($context), $context);
        return '('.implode(', ', $context).')';
    }
}
