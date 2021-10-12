<?php


namespace Opentalent\Optimizer\Middleware\Frontend;


use Opentalent\Optimizer\Website\WebsiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

class OtPageResolver extends \TYPO3\CMS\Frontend\Middleware\PageResolver
{
    /**
     * Resolve the page ID
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Opentalent\OtCore\Exception\NoSuchWebsiteException
     * @throws \TYPO3\CMS\Core\Error\Http\PageNotFoundException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $shallFallback = $_SERVER['TYPO3_OPTIMIZE'] != 1;
        if ($shallFallback) {
            return parent::process($request, $handler);
        }

        $websiteRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(WebsiteRepository::class);
        $devMode = $_SERVER['TYPO3_CONTEXT'] == "Development";

        if (!$GLOBALS['TYPO3_REQUEST']) {
            return GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $request,
                'The requested website does not exist',
                ['code' => PageAccessFailureReasons::PAGE_NOT_FOUND]
            );
        }

        $website = $GLOBALS['TYPO3_REQUEST']->getAttribute('website');
        $params = $request->getQueryParams();

        if (!$website['uid'] > 0) {
            return GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $request,
                'The requested website does not exist',
                ['code' => PageAccessFailureReasons::PAGE_NOT_FOUND]
            );
        }

        $pageUid = $websiteRepository->matchUriToPage($website, $request->getUri(), $devMode);
        if (!$pageUid > 0) {
            return GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $request,
                'The requested page does not exist',
                ['code' => PageAccessFailureReasons::PAGE_NOT_FOUND]
            );
        }

        $params['id'] = $pageUid;
        $request = $request->withQueryParams($params);

        return parent::process($request, $handler);
    }
}
