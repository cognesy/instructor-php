<?php

namespace Cognesy\Instructor\Experimental\Module\Modules\Text;

use Cognesy\Instructor\Experimental\Module\Modules\Prediction;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Experimental\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('text:string -> summary:string')]
#[ModuleDescription('Summarize the text')]
class SummarizeText extends Prediction {}
