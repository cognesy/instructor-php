<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Contracts;

use Cognesy\Experimental\RLM\Data\Handles\VarHandle;
use Cognesy\Experimental\RLM\Data\Repl\CodeResultHandle;
use Cognesy\Experimental\RLM\Data\Repl\ReplInventory;

/**
 * REPL-like working memory. Exposes variable/artifact handles — not raw content.
 */
interface ReplEnvironment
{
    public function inventory(): ReplInventory;
    public function runCode(string $code): CodeResultHandle;
    public function writeVar(string $name, mixed $value): VarHandle;
    public function readVar(string $name): VarHandle;
}

