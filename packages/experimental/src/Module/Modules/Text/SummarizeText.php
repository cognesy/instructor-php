<?php

namespace Cognesy\Experimental\Module\Modules\Text;

use Cognesy\Experimental\Module\Modules\Prediction;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('text:string -> summary:string')]
#[ModuleDescription('Summarize the text')]
class SummarizeText extends Prediction {}
