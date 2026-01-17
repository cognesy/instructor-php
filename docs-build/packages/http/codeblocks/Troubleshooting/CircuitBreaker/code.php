<?php declare(strict_types=1);

namespace Troubleshooting\CircuitBreaker;
class CircuitBreaker
{
    private $state = 'CLOSED';
    private $failures = 0;
    private $threshold = 5;
    private $timeout = 60;
    private $lastFailureTime = 0;

    public function execute(callable $operation) {
        if ($this->state === 'OPEN') {
            // Check if timeout has elapsed and we should try again
            if (time() - $this->lastFailureTime >= $this->timeout) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new \RuntimeException('Circuit is open');
            }
        }

        try {
            $result = $operation();

            // Reset on success
            if ($this->state === 'HALF_OPEN') {
                $this->reset();
            }

            return $result;

        } catch (\Exception $e) {
            $this->failures++;
            $this->lastFailureTime = time();

            // Open the circuit if we hit the threshold
            if ($this->failures >= $this->threshold || $this->state === 'HALF_OPEN') {
                $this->state = 'OPEN';
            }

            throw $e;
        }
    }

    public function reset() {
        $this->state = 'CLOSED';
        $this->failures = 0;
    }
}

// Usage
$circuitBreaker = new CircuitBreaker();

try {
    $response = $circuitBreaker->execute(function () use ($client, $request) {
        return $client->withRequest($request)->get();
    });

    // Process response
} catch (\RuntimeException $e) {
    if ($e->getMessage() === 'Circuit is open') {
        // Handle circuit open
        return $fallbackResponse;
    }

    // Handle other exceptions
    throw $e;
}
