<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Extras\Module\Core\Contracts\CanProcessCall;
use Cognesy\Instructor\Extras\Module\Core\Contracts\HasPendingExecution;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Call\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\Call\Enums\CallStatus;
use Cognesy\Instructor\Extras\Module\Call\Call;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\CallData\CallDataFactory;
use Cognesy\Instructor\Extras\Module\Utils\InputOutputMapper;
use Cognesy\Instructor\Utils\Result;
use Exception;


abstract class Module implements CanProcessCall
{
    use Traits\HandlesSignature;

    protected Signature $signature;

    public function with(HasInputOutputData $data) : HasPendingExecution {
        $call = $this->makeCall($data);
        $call->changeStatus(CallStatus::Ready);
        return $this->makePendingExecution($call);
    }

    public function withArgs(mixed ...$inputs) : HasPendingExecution {
        $call = $this->makeCallFromArgs(...$inputs);
        $call->changeStatus(CallStatus::Ready);
        return $this->makePendingExecution($call);
    }

    public function process(CanBeProcessed $call) : mixed {
        try {
            $call->changeStatus(CallStatus::InProgress);
            $result = $this->forward(...$call->data()->input()->getValues());
            $outputs = InputOutputMapper::toOutputs($result, $this->outputNames());
            $call->setOutputs($outputs);
            $call->changeStatus(CallStatus::Completed);
        } catch (Exception $e) {
            $call->addError($e->getMessage(), ['exception' => $e]);
            $call->changeStatus(CallStatus::Failed);
            throw $e;
        }
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    protected function makeCall(HasInputOutputData $data) : CanBeProcessed {
        return new Call($data);
    }

    protected function makeCallFromArgs(mixed ...$inputs) : CanBeProcessed {
        $callData = CallDataFactory::fromSignature($this->getSignature());
        $callData->withArgs(...$inputs);
        return $this->makeCall($callData);
    }

    protected function inputNames() : array {
        return $this->getSignature()->inputNames();
    }

    protected function outputNames() : array {
        return $this->getSignature()->outputNames();
    }

    protected function makePendingExecution(CanBeProcessed $call) : HasPendingExecution {
        return new class($this, $call) implements HasPendingExecution {
            private CanProcessCall $module;
            private array $sourceInputs;
            private CanBeProcessed $call;
            private bool $executed = false;
            private mixed $result;

            public function __construct(CanProcessCall $module, CanBeProcessed $call) {
                $this->module = $module;
                $this->call = $call;
                $this->sourceInputs = $call->inputs();
            }

            private function execute() : mixed {
                if ($this->executed) {
                    return $this->result;
                }
                $this->result = $this->module->process($this->call);
                $this->executed = true;
                return $this->result;
            }

            public function result() : mixed {
                return $this->execute();
            }

            public function try() : Result {
                try {
                    $data = $this->execute();
                    if ($this->call->hasErrors()) {
                        return Result::failure($this->call->errors());
                    }
                    return Result::success($data);
                } catch (Exception $e) {
                    return Result::failure($e);
                }
            }

            public function get(string $name = null) : mixed {
                $this->execute();
                if (empty($name)) {
                    return $this->call->outputs();
                }
                return $this->call->data()->output()->getPropertyValue($name);
            }

            public function asExample() : Example {
                $this->execute();
                return Example::fromData(
                    $this->sourceInputs,
                    $this->call->outputs(),
                );
            }

            public function hasErrors() : bool {
                return $this->call->hasErrors();
            }

            public function errors() : array {
                return $this->call->errors();
            }
        };
    }
}
