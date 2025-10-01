<?php
require 'vendor/autoload.php';

use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Utils\Time\FrozenClock;

echo "Creating clock...\n";
$clock = FrozenClock::at('2024-01-01 12:00:00');

echo "Creating state...\n";
$state = new ToolUseState();

echo "Creating ExecutionTimeLimit with clock as parameter...\n";
$limit = new ExecutionTimeLimit(30, fn(ToolUseState $s) => $s->startedAt(), $clock);

echo "Success! ExecutionTimeLimit created.\n";

echo "Testing canContinue...\n";
$canContinue = $limit->canContinue($state);
echo "canContinue result: " . ($canContinue ? 'true' : 'false') . "\n";