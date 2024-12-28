<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Text;

use Cognesy\Instructor\Extras\Module\Modules\Prediction;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleDescription;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\ModuleSignature;

#[ModuleSignature('text:string -> summary:string')]
#[ModuleDescription('Summarize the text')]
class SummarizeText extends Prediction {}
