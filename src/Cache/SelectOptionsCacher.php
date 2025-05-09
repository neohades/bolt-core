<?php

namespace Bolt\Cache;

use Bolt\Configuration\Config;
use Bolt\Entity\Field;
use Bolt\Repository\ContentRepository;
use Bolt\Storage\Query;
use Bolt\Twig\FieldExtension;
use Bolt\Twig\Notifications;
use Bolt\Utils\ContentHelper;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SelectOptionsCacher extends FieldExtension implements CachingInterface
{
    use CachingTrait;

    public const CACHE_CONFIG_KEY = 'selectoptions';

    private SessionInterface $session;
    private FieldExtension $decorated;

    public function __construct(
        SessionInterface $session,
        FieldExtension $decorated, // Symfony wstrzyknie dekorowaną usługę tutaj
        Notifications $notifications,
        ContentRepository $contentRepository,
        Config $config,
        ContentHelper $contentHelper,
        Query $query
    ) {
        parent::__construct($notifications, $contentRepository, $config, $contentHelper, $query);
        $this->session = $session;
        $this->decorated = $decorated;
    }

    public function selectOptionsHelper(string $contentTypeSlug, array $params, Field $field, string $format): array
    {
        $activeMicroservice = $this->session->get('CURRENT_SERVICE', 'default');
        // var_dump($activeMicroservice);

        $this->setCacheKey([$activeMicroservice, $contentTypeSlug, $format] + $params);
        $this->setCacheTags($this->getTags($contentTypeSlug));

        return $this->execute([$this->decorated, __FUNCTION__], [$contentTypeSlug, $params, $field, $format]);
    }

    /**
     * Make sure something like `(pages,entries)` becomes an array like ['pages', 'entries']
     */
    private function getTags(string $contentTypeSlug): array
    {
        $tags = explode(',', $contentTypeSlug);

        $tags = array_map(function($t) {
            return preg_replace('/[^\pL\d,]+/u', '', $t);
        }, $tags);

        return $tags;
    }
}