<?php
namespace Opentalent\Optimizer\XClass\Core\Routing;

use Doctrine\DBAL\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendWorkspaceRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Override the default core PageSlugCandidateProvider of typo3 to exclude the pages
 * which do not belong to the current website
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Xclasses/Index.html
 * @package Opentalent\Optimizer\Routing
 */
class OtPageSlugCandidateProvider extends PageSlugCandidateProvider
{
    /**
     * special: patch 2020-09-22 by Opentalent, for performances reason
     * Returns an array containing all the subpages'uids, including the given $pageId
     * >> made for typo3 v9.5
     *
     * @param int $pageId
     * @return array|int[]
     */
    private function getAllSubpagesUidFor(int $pageId) {
        $subpages = [$pageId];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', (int)$pageId)
            );

        $statement = $queryBuilder->execute();

        while ($row = $statement->fetch()) {
            $subpages = array_merge($subpages, $this->getAllSubpagesUidFor($row['uid']));
        }

        return $subpages;
    }

    /**
     * -- Override the original method from the typo3 PageSlugCandidateProvider --
     * Check for records in the database which matches one of the slug candidates.
     *
     * @param array $slugCandidates
     * @param int $languageId
     * @param array $excludeUids when called recursively this is the mountpoint parameter of the original prefix
     * @return array
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Context\Exception\AspectNotFoundException
     */
    protected function getPagesFromDatabaseForCandidates(array $slugCandidates, int $languageId, array $excludeUids = []): array
    {
        $searchLiveRecordsOnly = $this->context->getPropertyFromAspect('workspace', 'isLive');
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(FrontendWorkspaceRestriction::class, null, null, $searchLiveRecordsOnly));

        $statement = $queryBuilder
            ->select('uid', 'l10n_parent', 'pid', 'slug', 'mount_pid', 'mount_pid_ol', 't3ver_state', 'doktype', 't3ver_wsid', 't3ver_oid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'slug',
                    $queryBuilder->createNamedParameter(
                        $slugCandidates,
                        Connection::PARAM_STR_ARRAY
                    )
                )
            )
            // Exact match will be first, that's important
            ->orderBy('slug', 'desc')
            // Sort pages that are not MountPoint pages before mount points
            ->addOrderBy('mount_pid_ol', 'asc')
            ->addOrderBy('mount_pid', 'asc')
            ->execute();

        $pages = [];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $this->context);
        $isRecursiveCall = !empty($excludeUids);

        // <----
        // special: patch 2021-10-05 by Opentalent, for performances reason
        // >> made for typo3 v10.4
        $siteRootPageUid = $this->site->getRootPageId();
        $sitePages = $this->getAllSubpagesUidFor($siteRootPageUid);
        // ---->

        while ($row = $statement->fetch()) {
            $mountPageInformation = null;
            // This changes the PID value and adds a _ORIG_PID value (only different in move actions)
            // In live: This fetches everything in a bad way ! as there is no workspace limitation given, fetching all new and moved placeholders here!
            // In a workspace: Filter out versioned records (t3ver_oid=0), leaving effectively the new/move placeholders in place, where the new placeholder
            // However, this is checked in $siteFinder->getSiteByPageId() via RootlineUtility where overlays are happening
            // so the fixVersioningPid() call is probably irrelevant.
            $pageRepository->fixVersioningPid('pages', $row);
            $pageIdInDefaultLanguage = (int)($languageId > 0 ? $row['l10n_parent'] : $row['uid']);
            // When this page was added before via recursion, this page should be skipped
            if (in_array($pageIdInDefaultLanguage, $excludeUids, true)) {
                continue;
            }

            // <----
            // special: patch 2021-10-05 by Opentalent, for performances reason
            // >> made for typo3 v10.4
            if (!in_array($pageIdInDefaultLanguage, $sitePages)) {
                continue;
            }
            // ---->

            try {
                $isOnSameSite = $siteFinder->getSiteByPageId($pageIdInDefaultLanguage)->getRootPageId() === $this->site->getRootPageId();
            } catch (SiteNotFoundException $e) {
                // Page is not in a site, so it's not considered
                $isOnSameSite = false;
            }

            // If a MountPoint is found on the current site, and it hasn't been added yet by some other iteration
            // (see below "findPageCandidatesOfMountPoint"), then let's resolve the MountPoint information now
            if (!$isOnSameSite && $isRecursiveCall) {
                // Not in the same site, and called recursive, should be skipped
                continue;
            }
            $mountPageInformation = $pageRepository->getMountPointInfo($pageIdInDefaultLanguage, $row);

            // Mount Point Pages which are not on the same site (when not called on the first level) should be skipped
            // As they just clutter up the queries.
            if (!$isOnSameSite && !$isRecursiveCall && $mountPageInformation) {
                continue;
            }

            $mountedPage = null;
            if ($mountPageInformation) {
                // Add the MPvar to the row, so it can be used later-on in the PageRouter / PageArguments
                $row['MPvar'] = $mountPageInformation['MPvar'];
                $mountedPage = $pageRepository->getPage_noCheck($mountPageInformation['mount_pid_rec']['uid']);
                // Ensure to fetch the slug in the translated page
                $mountedPage = $pageRepository->getPageOverlay($mountedPage, $languageId);
                // Mount wasn't connected properly, so it is skipped
                if (!$mountedPage) {
                    continue;
                }
                // If the page is a MountPoint which should be overlaid with the contents of the mounted page,
                // it must never be accessible directly, but only in the MountPoint context. Therefore we change
                // the current ID and slug.
                // This needs to happen before the regular case, as the $pageToAdd contains the MPvar information
                if (PageRepository::DOKTYPE_MOUNTPOINT === (int)$row['doktype'] && $row['mount_pid_ol']) {
                    // If the mounted page was already added from above, this should not be added again (to include
                    // the mount point parameter).
                    if (in_array((int)$mountedPage['uid'], $excludeUids, true)) {
                        continue;
                    }
                    $pageToAdd = $mountedPage;
                    // Make sure target page "/about-us" is replaced by "/global-site/about-us" so router works
                    $pageToAdd['MPvar'] = $mountPageInformation['MPvar'];
                    $pageToAdd['slug'] = $row['slug'];
                    $pages[] = $pageToAdd;
                    $excludeUids[] = (int)$pageToAdd['uid'];
                    $excludeUids[] = $pageIdInDefaultLanguage;
                }
            }

            // This is the regular "non-MountPoint page" case (must happen after the if condition so MountPoint
            // pages that have been replaced by the Mounted Page will not be added again.
            if ($isOnSameSite && !in_array($pageIdInDefaultLanguage, $excludeUids, true)) {
                $pages[] = $row;
                $excludeUids[] = $pageIdInDefaultLanguage;
            }

            // Add possible sub-pages prepended with the MountPoint page slug
            if ($mountPageInformation) {
                /** @var array $mountedPage */
                $siteOfMountedPage = $siteFinder->getSiteByPageId((int)$mountedPage['uid']);
                $morePageCandidates = $this->findPageCandidatesOfMountPoint(
                    $row,
                    $mountedPage,
                    $siteOfMountedPage,
                    $languageId,
                    $slugCandidates
                );
                foreach ($morePageCandidates as $candidate) {
                    // When called previously this MountPoint page should be skipped
                    if (in_array((int)$candidate['uid'], $excludeUids, true)) {
                        continue;
                    }
                    $pages[] = $candidate;
                }
            }
        }
        return $pages;

    }

}
