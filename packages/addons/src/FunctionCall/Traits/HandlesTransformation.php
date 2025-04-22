<?php
namespace Cognesy\Addons\FunctionCall\Traits;

trait HandlesTransformation
{
    public function transform() : mixed {
        return $this->toArgs();
    }

    public function toArgs(): array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[$field->name()] = $field->get();
        }
        return $arguments;
    }
}