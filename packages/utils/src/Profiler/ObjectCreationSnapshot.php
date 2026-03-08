<?php declare(strict_types=1);

namespace Cognesy\Utils\Profiler;

final readonly class ObjectCreationSnapshot
{
    /**
     * @param array<string, int> $createdByClass
     * @param array<string, int> $liveByClass
     */
    public function __construct(
        public string $label,
        public int $memoryUsage,
        public int $realMemoryUsage,
        public int $createdTotal,
        public int $liveTotal,
        public array $createdByClass,
        public array $liveByClass,
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'memoryUsage' => $this->memoryUsage,
            'realMemoryUsage' => $this->realMemoryUsage,
            'createdTotal' => $this->createdTotal,
            'liveTotal' => $this->liveTotal,
            'createdByClass' => $this->createdByClass,
            'liveByClass' => $this->liveByClass,
        ];
    }
}
