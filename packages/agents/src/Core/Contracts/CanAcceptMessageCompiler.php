<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

/**
 * Implemented by drivers that can have their message compiler injected or swapped.
 */
interface CanAcceptMessageCompiler
{
    public function messageCompiler(): CanCompileMessages;

    public function withMessageCompiler(CanCompileMessages $compiler): static;
}
