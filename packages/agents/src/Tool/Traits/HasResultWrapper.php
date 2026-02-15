<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Utils\Result\Result;
use Throwable;

trait HasResultWrapper
{
    #[\Override]
    public function use(mixed ...$args): Result {
        try {
            $value = $this->__invoke(...$args);
        } catch (AgentStopException $e) {
            throw $e;
        } catch (Throwable $e) {
            return Result::failure($e);
        }

        return Result::success($value);
    }
}
