<?php
defined('TYPO3_MODE') || die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Core\Routing\PageSlugCandidateProvider::class] = [
    'className' => Opentalent\Optimizer\XClass\Core\Routing\OtPageSlugCandidateProvider::class
];

// \TYPO3\CMS\Frontend\Middleware\SiteResolver is overridden but not xclassed here; instead it is
//   replaced in the middlewares array (@see ot_optimizer/Configuration/RequestMiddlewares.php)

// \TYPO3\CMS\Frontend\Middleware\PageResolver is overridden but not xclassed here; instead it is
//   replaced in the middlewares array (@see ot_optimizer/Configuration/RequestMiddlewares.php)
