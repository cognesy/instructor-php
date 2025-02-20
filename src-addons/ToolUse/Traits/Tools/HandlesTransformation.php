<?php

namespace Cognesy\Addons\ToolUse\Traits\Tools;

trait HandlesTransformation
{
    public function toToolSchema() : array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }
}