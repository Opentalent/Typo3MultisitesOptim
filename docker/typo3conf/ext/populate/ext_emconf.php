<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "Populate"
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Populate',
    'description' => 'Populate the db with many basic websites',
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
