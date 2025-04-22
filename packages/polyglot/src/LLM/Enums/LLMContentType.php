<?php

namespace Cognesy\Polyglot\LLM\Enums;

enum LLMContentType : string
{
    case Text = 'text';
    case Thinking = 'thinking';
    case Citation = 'citation';
}