<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\Builder\Collections\DeferredToolProviders;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Events\Contracts\CanHandleEvents;

interface CanConfigureAgent
{
    // TOOLS ////////////////////////////////////////////////////////
    public function tools(): Tools;

    public function withTools(Tools $tools): self;

    // DEFERRED TOOLS ////////////////////////////////////////////////
    public function deferredTools(): DeferredToolProviders;

    public function withDeferredTools(DeferredToolProviders $deferredTools): self;

    // CONTEXT COMPILER /////////////////////////////////////////////
    public function contextCompiler(): CanCompileMessages;

    public function withContextCompiler(CanCompileMessages $compiler): self;

    // TOOL USE DRIVER //////////////////////////////////////////////
    public function toolUseDriver(): CanUseTools;

    public function withToolUseDriver(CanUseTools $driver): self;

    // HOOKS ////////////////////////////////////////////////////////
    public function hooks(): HookStack;

    public function withHooks(HookStack $hooks): self;

    // EVENTS ////////////////////////////////////////////////////////
    public function events(): CanHandleEvents;
}
