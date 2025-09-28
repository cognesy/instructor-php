<?php

namespace Cognesy\Experimental\Modules\Text;

use Cognesy\Experimental\Module\Modules\Prediction;
use Cognesy\Experimental\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Signature\Attributes\ModuleSignature;

#[ModuleSignature('text:string -> summary:string')]
#[ModuleDescription('Summarize the text')]
class SummarizeText extends Prediction {}
