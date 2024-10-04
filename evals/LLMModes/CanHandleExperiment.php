<?php

namespace Cognesy\Evals\LLMModes;

interface CanHandleExperiment
{
    public function handle(): EvalOutput;
}