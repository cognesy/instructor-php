<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Extras\Tasks;

final readonly class TodoPolicy
{
    public function __construct(
        public int $maxItems = 20,
        public int $maxInProgress = 1,
        public int $renderEverySteps = 10,
        public int $reminderEverySteps = 10,
    ) {}
}
