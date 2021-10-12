<?php
/**
 * Commands to be executed by typo3, where the key of the array
 * is the name of the command (to be called as the first argument after typo3).
 * Required parameter is the "class" of the command which needs to be a subclass
 * of Symfony/Console/Command.
 */

// /!\ WARNING: this way of register commands will be deprecated with Typo3 v10
// https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/CommandControllers/Index.html#creating-a-new-command-in-extensions

return [
    'ot:populate' => [
        'class' => Opentalent\Populate\Command\PopulateCommand::class
    ],
    'ot:clear-db' => [
        'class' => Opentalent\Populate\Command\ClearDbCommand::class
    ]
];

