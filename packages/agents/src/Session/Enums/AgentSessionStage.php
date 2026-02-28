<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Enums;

enum AgentSessionStage: string
{
    case AfterLoad = 'after_load';
    case AfterAction = 'after_action';
    case BeforeSave = 'before_save';
    case AfterSave = 'after_save';
}
