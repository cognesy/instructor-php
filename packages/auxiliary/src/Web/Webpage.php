<?php
namespace Cognesy\Auxiliary\Web;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Cognesy\Auxiliary\Web\Html\HtmlProcessor;
use Cognesy\Auxiliary\Web\Scrapers\BasicReader;
use Cognesy\Auxiliary\Web\Traits\HandlesContent;
use Cognesy\Auxiliary\Web\Traits\HandlesCreation;
use Cognesy\Auxiliary\Web\Traits\HandlesExtraction;
use Cognesy\Auxiliary\Web\Traits\HandlesLinks;
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
        ?CanGetUrlContent $scraper = null,
    ) {
        $this->scraper = $scraper ?? new BasicReader();
        $this->htmlProcessor = new HtmlProcessor();
    }

    public function toMessage(): Message {
        return new Message(content: $this->asMarkdown());
    }
}
