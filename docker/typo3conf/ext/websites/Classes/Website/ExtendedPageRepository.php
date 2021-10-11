<?php

namespace Opentalent\Websites\Website;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class OtPageRepository
 *
 * Provides some useful methods to query typo3 pages
 *
 * @package Opentalent\OtCore\Page
 */
class ExtendedPageRepository
{
    /**
     * @var \TYPO3\CMS\Core\Domain\Repository\PageRepository
     */
    private \TYPO3\CMS\Core\Domain\Repository\PageRepository $pageRepository;

    public function injectPageRepository(\TYPO3\CMS\Core\Domain\Repository\PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    private \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool;

    public function injectConnectionPool(\TYPO3\CMS\Core\Database\ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Returns all the subpages of the given page
     *
     * @param int $pid The uid of the parent page
     * @param bool $withRestrictions Set to true to add the standard restrictions (deleted, forbidden...etc.)
     * @return array
     */
    public function getPagesByPid(int $pid, bool $withRestrictions=false): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        if (!$withRestrictions) {
            $queryBuilder->getRestrictions()->removeAll();
        }
        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('pid', $pid))
            ->execute()
            ->fetchAll();
    }

    /**
     * Returns the root page of the given page website,
     * or the page itself if the given page is
     * already the rootpage of the site
     *
     * @param $pageUid
     *
     * @return array
     */
    public function getRootPageFor($pageUid): array
    {
        $page = $this->getPage($pageUid);

        while ($page && $page['is_siteroot'] !== 1) {
            $page = $this->getPage($page['pid']);
        }
        return $page;
    }

    /**
     * Recursively returns all the subpages of the given page
     *
     * @param int $pageUid The uid of the parent page
     * @param bool $withRestrictions Set to true to add the standard restrictions (deleted, forbidden...etc.)
     * @return array
     */
    public function getAllSubpagesForPage(int $pageUid, bool $withRestrictions=false): array
    {
        $subpages = [];

        $stack = $this->getPagesByPid($pageUid, $withRestrictions);

        foreach ($stack as $page) {
            $subpages[] = $page;
            $children = $this->getAllSubpagesForPage($page['uid']);
            if (!empty($children)) {
                $subpages = array_merge($subpages, $children);
            }
        }
        return $subpages;
    }

    /**
     * Returns all the pages of the given page's website, starting from the root page
     *
     * @param int $pageUid
     * @param bool $withRestrictions Set to true to add the standard restrictions (deleted, forbidden...etc.)
     * @return array
     */
    public function getPageWithSubpages(int $pageUid, bool $withRestrictions=false): array
    {
        return array_merge([$this->getPage($pageUid)], $this->getAllSubpagesForPage($pageUid, $withRestrictions));
    }

    public function getPage(int $uid): array
    {
        return $this->pageRepository->getPage($uid, true);
    }
}
