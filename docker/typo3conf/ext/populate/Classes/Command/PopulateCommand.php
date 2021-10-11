<?php

namespace Opentalent\Populate\Command;


use Opentalent\Populate\Controller\SiteController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * This CLI command creates as many websites as requested (default: 3000)
 */
class PopulateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("ot:populate")
            ->setDescription("Create as many websites as requested (default: 3000)")
            ->addArgument(
                'number',
                InputArgument::OPTIONAL,
                "Number of websites to create",
                3000
            );
    }

    /**
     * -- This method is expected by Typo3, do not rename ou remove --
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $number = $input->getArgument('number') - 1;
        if ($number <= 0) {
            $io->error('number shall be greater or equal to 1');
            return 1;
        }

        $siteController = GeneralUtility::makeInstance(ObjectManager::class)->get(SiteController::class);

        $io->progressStart($number);
        foreach (range(0, $number) as $number) {
            $siteController->createSiteAction();
            $io->progressAdvance(1);
        }
        $io->progressFinish();

        $io->info(sprintf("Clearing cache..."));
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->flushCaches();

        $io->success(sprintf("Websites have been created"));
        return 1;
    }
}
