<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Handles;

/** Marker interface for logical handles (variables, artifacts, results). */
interface Handle
{
    public function id(): string;
}

