<?php

/**
 * Register middlewares, which will be triggered at each request
 */
return [
    'frontend' => [
        'typo3/cms-frontend/site' => [
            'target' => Opentalent\Optimizer\Middleware\Frontend\SiteResolver::class,
            'before' => [
                'typo3/cms-frontend/page-resolver'
            ]
        ],
        'typo3/cms-frontend/page-resolver' => [
            'target' => Opentalent\Optimizer\Middleware\Frontend\PageResolver::class,
            'before' => [
                'typo3/frontendediting/initiator'
            ],
            'after' => [
                'typo3/cms-frontend/preview-simulator'
            ],
        ],
    ],
];
