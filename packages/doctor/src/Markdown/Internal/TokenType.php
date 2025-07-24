<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

enum TokenType: string
{
    case Header = 'header';
    case CodeBlockFenceStart = 'codeblock_fence_start';
    case CodeBlockFenceEnd = 'codeblock_fence_end';
    case CodeBlockFenceInfo = 'codeblock_fence_info';
    case CodeBlockContent = 'codeblock_content';
    case Content = 'content';
    case Newline = 'newline';
}