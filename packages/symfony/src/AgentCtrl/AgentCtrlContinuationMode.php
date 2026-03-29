<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

enum AgentCtrlContinuationMode: string
{
    case Fresh = 'fresh';
    case ContinueLast = 'continue_last';
    case ResumeSession = 'resume_session';
}
