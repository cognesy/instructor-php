<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Branch\TellBranch;
use Cognesy\Tell\Branch\TellBranches;
use Cognesy\Tell\Branch\TellRef;
use Cognesy\Tell\TellConversation;

/** Opens purpose-built canonical conversation facades without exposing stores. */
interface CanAccessTellConversations
{
    public function main(string $directory): TellConversation;

    public function conversation(string $directory, string $name): TellConversation;

    public function branches(string $directory): TellBranches;

    public function branch(string $directory, string $name): TellBranch;

    public function ref(string $directory, string $hash): TellRef;
}
