<?php
declare(strict_types = 1);
namespace Opentalent\OtOptimizer\Middleware\Frontend;

use Opentalent\OtOptimizer\Exception\NoSuchWebsiteException;
use Opentalent\OtOptimizer\Website\OtWebsiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

/**
 *
 */
class OtSiteResolver extends \TYPO3\CMS\Frontend\Middleware\SiteResolver
{
    /**
     * Resolve the site/language information by checking the page ID or the URL.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $otWebsiteRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(OtWebsiteRepository::class);

        try {
            $devMode = $_SERVER['TYPO3_CONTEXT'] == "Development";

            $otWebsite = $otWebsiteRepository->matchUriToWebsite($request->getUri(), $devMode);
            $site = $otWebsiteRepository->generateWebsiteConfiguration($otWebsite);
            $language = $site->getDefaultLanguage();
            if ($devMode) {
                preg_match("/\w+\/(.*)/", $request->getUri()->getPath(), $m);
                $tail = $m[1] ?? "";
            } else {
                $tail = rtrim($request->getUri()->getPath(), '/');
            }
        } catch (NoSuchWebsiteException $e) {
            // site not found
            // either it will be redirected, or it will return a pageNotFound error during the page resolution
            return $handler->handle($request);
        }

        $routeResult = new SiteRouteResult($request->getUri(), $site, $language, $tail);

        $request = $request->withAttribute('ot_website', $otWebsite);
        $request = $request->withAttribute('site', $routeResult->getSite());
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);

        // At this point, we later get further route modifiers
        // for bw-compat we update $GLOBALS[TYPO3_REQUEST] to be used later in TSFE.
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $handler->handle($request);
    }
}
