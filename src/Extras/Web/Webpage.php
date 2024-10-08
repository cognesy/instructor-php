<?php
namespace Cognesy\Instructor\Extras\Web;

use Cognesy\Instructor\Contracts\CanProvideMessage;
use Cognesy\Instructor\Extras\Web\Contracts\CanGetUrlContent;
use Cognesy\Instructor\Extras\Web\Html\HtmlProcessor;
use Cognesy\Instructor\Extras\Web\Scrapers\BasicReader;
use Cognesy\Instructor\Utils\Messages\Message;

class Webpage implements CanProvideMessage
{
    use Traits\HandlesContent;
    use Traits\HandlesCreation;
    use Traits\HandlesExtraction;
    use Traits\HandlesLinks;

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
