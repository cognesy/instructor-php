<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

enum StreamCaptureMode: string
{
    case None = 'none';
    case Preview = 'preview';
    case Chunks = 'chunks';
    case Full = 'full';
}
