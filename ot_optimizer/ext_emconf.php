<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "ot_optimizer"
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Optimizer',
    'description' => 'Optimize the Typo3 FE and BE behaviour for large amount of sites',
    'category' => 'services',
    'author' => 'Olivier Massot',
    'author_email' => 'olivier.massot@2iopenservice.fr',
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-10.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
