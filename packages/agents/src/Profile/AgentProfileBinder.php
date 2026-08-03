<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\RegisteredHook;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Profile\Contracts\CanAcceptAgentProfile;
use Cognesy\Agents\Tool\Contracts\ToolInterface;

final readonly class AgentProfileBinder
{
    public static function tools(Tools $tools, AgentProfile $profile): Tools {
        $bound = [];
        foreach ($tools->all() as $tool) {
            $bound[] = match (true) {
                $tool instanceof CanAcceptAgentProfile => $tool->withAgentProfile($profile),
                default => $tool,
            };
        }
        /** @var list<ToolInterface> $bound */
        return new Tools(...$bound);
    }

    public static function driver(CanUseTools $driver, AgentProfile $profile): CanUseTools {
        if (!$driver instanceof CanAcceptAgentProfile) {
            return $driver;
        }
        $bound = $driver->withAgentProfile($profile);
        /** @var CanUseTools $bound */
        return $bound;
    }

    public static function interceptor(
        CanInterceptAgentLifecycle $interceptor,
        AgentProfile $profile,
    ): CanInterceptAgentLifecycle {
        if ($interceptor instanceof HookStack) {
            return $interceptor->mapHooks(
                static fn (RegisteredHook $registered): RegisteredHook => self::registeredHook($registered, $profile),
            );
        }
        if (!$interceptor instanceof CanAcceptAgentProfile) {
            return $interceptor;
        }
        $bound = $interceptor->withAgentProfile($profile);
        /** @var CanInterceptAgentLifecycle $bound */
        return $bound;
    }

    private static function registeredHook(RegisteredHook $registered, AgentProfile $profile): RegisteredHook {
        $hook = $registered->hook();
        if (!$hook instanceof CanAcceptAgentProfile) {
            return $registered;
        }
        $bound = $hook->withAgentProfile($profile);
        /** @var HookInterface $bound */
        return $registered->withHook($bound);
    }
}
