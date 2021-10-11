<?php

namespace Opentalent\Websites\Controller;

use Opentalent\Websites\Website\ExtendedPageRepository;
use Opentalent\Websites\Website\WebsiteRepository;

/**
 * Base class for all controllers of backend modules
 */
class ActionController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var WebsiteRepository
     */
    protected WebsiteRepository $websiteRepository;

    public function injectWebsiteRepository(WebsiteRepository $websiteRepository) {
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * @var ExtendedPageRepository
     */
    protected ExtendedPageRepository $extendedPageRepository;

    public function injectExtendedPageRepository(ExtendedPageRepository $extendedPageRepository) {
        $this->extendedPageRepository = $extendedPageRepository;
    }
}
