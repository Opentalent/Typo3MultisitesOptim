<?php

namespace Opentalent\Populate\Command;


use Opentalent\Populate\Controller\SiteController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * This CLI command creates as many websites as requested (default: 3000)
 */
class ClearDbCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("ot:clear-db")
            ->setDescription("Clear the db of all the sites created with the populate command");
    }

    /**
     * -- This method is expected by Typo3, do not rename ou remove --
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $siteController = GeneralUtility::makeInstance(ObjectManager::class)->get(SiteController::class);

        $siteController->clearDbAction();
        $io->info(sprintf("DB cleared"));

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->flushCaches();
        $io->info(sprintf("Cache cleared"));

        $io->success(sprintf("The database has been successfully cleared"));
        return 1;
    }
}
