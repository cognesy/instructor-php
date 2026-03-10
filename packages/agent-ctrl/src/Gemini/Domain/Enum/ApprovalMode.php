<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Enum;

/**
 * Approval mode for Gemini CLI (maps to --approval-mode flag)
 */
enum ApprovalMode: string
{
    case Default = 'default';
    case AutoEdit = 'auto_edit';
    case Yolo = 'yolo';
    case Plan = 'plan';
}
