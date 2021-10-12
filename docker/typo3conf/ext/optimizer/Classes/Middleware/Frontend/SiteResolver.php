<?php
declare(strict_types = 1);
namespace Opentalent\Optimizer\Middleware\Frontend;

use Opentalent\Websites\Exception\NoSuchWebsiteException;
use Opentalent\Websites\Website\WebsiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Routing\SiteRouteResult;

/**
 *
 */
class SiteResolver extends \TYPO3\CMS\Frontend\Middleware\SiteResolver
{
    /**
     * Resolve the site/language information by checking the page ID or the URL.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $websiteRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(WebsiteRepository::class);

        try {
            $website = $websiteRepository->matchUriToWebsite($request->getUri());
            $site = $websiteRepository->generateWebsiteConfiguration($website);
            $language = $site->getDefaultLanguage();

            preg_match("/\w+\/(.*)/", $request->getUri()->getPath(), $m);
            $tail = $m[1] ?? "";
        } catch (NoSuchWebsiteException $e) {
            // site not found
            // either it will be redirected, or it will return a pageNotFound error during the page resolution
            return $handler->handle($request);
        }

        $routeResult = new SiteRouteResult($request->getUri(), $site, $language, $tail);

        $request = $request->withAttribute('website', $website);
        $request = $request->withAttribute('site', $routeResult->getSite());
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);

        // At this point, we later get further route modifiers
        // for bw-compat we update $GLOBALS[TYPO3_REQUEST] to be used later in TSFE.
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $handler->handle($request);
    }
}
