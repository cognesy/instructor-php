<?php
namespace Cognesy\Aux\Web;

use Cognesy\Aux\Web\Contracts\CanGetUrlContent;
use Cognesy\Aux\Web\Html\HtmlProcessor;
use Cognesy\Aux\Web\Scrapers\BasicReader;
use Cognesy\Aux\Web\Traits\HandlesContent;
use Cognesy\Aux\Web\Traits\HandlesCreation;
use Cognesy\Aux\Web\Traits\HandlesExtraction;
use Cognesy\Aux\Web\Traits\HandlesLinks;
use Cognesy\Utils\Messages\Contracts\CanProvideMessage;
use Cognesy\Utils\Messages\Message;

class Webpage implements CanProvideMessage
{
    use HandlesContent;
    use HandlesCreation;
    use HandlesExtraction;
    use HandlesLinks;

    protected CanGetUrlContent $scraper;
    protected HtmlProcessor $htmlProcessor;
    protected string $content;
    protected string $url;
    /** @var Link[] */
    protected array $links = [];

    public function __construct(
        CanGetUrlContent $scraper = null,
    ) {
        $this->scraper = $scraper ?? new BasicReader();
        $this->htmlProcessor = new HtmlProcessor();
    }

    public function toMessage(): Message {
        return new Message(content: $this->asMarkdown());
    }
}
