<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Support;

use Iterator;

final readonly class Tee
{
    private function __construct() {}

    /**
     * Split a single-pass iterable into N independent iterators.
     * Branches can consume at different speeds. Data is buffered
     * until all active branches advance past it. No rewind() is used.
     *
     * Note: If a branch is abandoned early (consumer stops), its
     * iterator is closed and the buffer can evict based on remaining
     * active branches only.
     *
     * @return array<int, Iterator>
     */
    public static function split(iterable $source, int $branches = 2): array
    {
        if ($branches < 1) {
            throw new \InvalidArgumentException('branches must be >= 1');
        }

        $state = new class(IteratorUtils::toIterator($source), $branches) {
            /** @var Iterator */
            private Iterator $source;
            /** @var array<int, mixed> */
            private array $buffer = [];
            private int $head = 0; // logical index of first buffered item
            private int $tail = 0; // logical index after last buffered item
            /** @var array<int, int> */
            private array $cursor = [];
            /** @var array<int, bool> */
            private array $active = [];
            private bool $primed = false;
            private bool $done = false;

            public function __construct(Iterator $source, int $branches)
            {
                $this->source = $source;
                for ($i = 0; $i < $branches; $i++) {
                    $this->cursor[$i] = $this->head;
                    $this->active[$i] = true;
                }
            }

            public function nextValue(int $id): mixed
            {
                if (!$this->active[$id]) {
                    return null;
                }

                if ($this->cursor[$id] < $this->tail) {
                    $index = $this->cursor[$id];
                    $this->cursor[$id] = $index + 1;
                    $value = $this->buffer[$index];
                    $this->evict();
                    return $value;
                }

                if (!$this->appendFromSource()) {
                    return null;
                }

                $this->cursor[$id] = $this->tail; // will read last appended
                $index = $this->tail - 1;
                $value = $this->buffer[$index];
                $this->evict();
                return $value;
            }

            public function deactivate(int $id): void
            {
                $this->active[$id] = false;
                $this->evict();
            }

            private function appendFromSource(): bool
            {
                if ($this->done) {
                    return false;
                }

                if (!$this->primed) {
                    if (!$this->source->valid()) {
                        $this->done = true;
                        return false;
                    }
                    $value = $this->source->current();
                    $this->source->next();
                    $this->primed = true;
                    $this->buffer[$this->tail] = $value;
                    $this->tail++;
                    return true;
                }

                if (!$this->source->valid()) {
                    $this->done = true;
                    return false;
                }

                $value = $this->source->current();
                $this->source->next();
                $this->buffer[$this->tail] = $value;
                $this->tail++;
                return true;
            }

            private function evict(): void
            {
                $min = null;
                foreach ($this->cursor as $id => $pos) {
                    if (!$this->active[$id]) {
                        continue;
                    }
                    $min = ($min === null) ? $pos : min($min, $pos);
                }
                if ($min === null) {
                    // no active branches, clear buffer
                    $this->buffer = [];
                    $this->head = $this->tail;
                    return;
                }
                if ($min <= $this->head) {
                    return;
                }
                for ($i = $this->head; $i < $min; $i++) {
                    unset($this->buffer[$i]);
                }
                $this->head = $min;
            }
        };

        $make = function (int $id) use ($state): Iterator {
            try {
                while (true) {
                    $value = $state->nextValue($id);
                    if ($value === null) {
                        break;
                    }
                    yield $value;
                }
            } finally {
                $state->deactivate($id);
            }
        };

        $out = [];
        for ($i = 0; $i < $branches; $i++) {
            $out[] = $make($i);
        }
        return $out;
    }
}