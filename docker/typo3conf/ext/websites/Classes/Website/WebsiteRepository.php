<?php

namespace Opentalent\Websites\Website;

use Opentalent\Websites\Exception\InvalidWebsiteConfigurationException;
use Opentalent\Websites\Exception\NoSuchRecordException;
use Opentalent\Websites\Exception\NoSuchWebsiteException;
use Opentalent\Websites\Utility\RouteNormalizer;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Repository for the websites table
 */
class WebsiteRepository
{
    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    private \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool;

    public function injectConnectionPool(\TYPO3\CMS\Core\Database\ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Get the root page uid of the given website
     *
     * @param int $websiteUid
     * @param bool $withRestrictions
     * @return int
     * @throws NoSuchRecordException
     */
    public function getWebsiteRootUid(int $websiteUid, bool $withRestrictions = true): int {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        if (!$withRestrictions) {
            $queryBuilder->getRestrictions()->removeAll();
        }
        $q = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('website_uid', $websiteUid))
            ->andWhere($queryBuilder->expr()->eq('is_siteroot', 1));
        if ($withRestrictions) {
            $q->andWhere($q->expr()->eq('deleted', 0));
        }
        $rootUid = $q->execute()->fetchColumn(0);
        if (!$rootUid > 0) {
            throw new NoSuchRecordException('No root page found for website ' . $websiteUid);
        }
        return $rootUid;
    }

    /**
     * Get the Website of the given page
     *
     * @throws \Opentalent\OtCore\Exception\NoSuchWebsiteException
     */
    public function getWebsiteByPageUid(int $pageUid, bool $withRestrictions = true): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
        if (!$withRestrictions) {
            $queryBuilder->getRestrictions()->removeAll();
        }
        $q = $queryBuilder
            ->select('w.*')
            ->from('websites', 'w')
            ->innerJoin('w', 'pages', 'p', $queryBuilder->expr()->eq('p.website_uid', 'w.uid'))
            ->where($queryBuilder->expr()->eq('p.uid', $pageUid));
        if ($withRestrictions) {
            $q->andWhere($q->expr()->eq('w.deleted', 0));
        }
        $website = $q->execute()->fetch();
        if (!isset($website['uid'])) {
            throw new NoSuchWebsiteException('No website found for page ' . $pageUid);
        }
        return $website;
    }

    /**
     * Retrieves the current full domain of the given website.
     *
     * @param array $website
     * @return string
     * @throws InvalidWebsiteConfigurationException
     */
    public function resolveWebsiteDomain(array $website): string
    {
        return $website['subdomain'] . '.mydomain.fr';
    }

    /**
     * Get the website current base uri
     *
     * @param array $website
     * @return string
     * @throws InvalidWebsiteConfigurationException
     */
    public function resolveWebsiteBaseUri(array $website): string
    {
        return 'https://' . $this->resolveWebsiteDomain($website);
    }

    /**
     * Generate an array as it would be loaded from the site.yaml configuration
     * file of the given website
     *
     * @param array $website
     * @param string|null $identifier
     * @return Site
     * @throws InvalidWebsiteConfigurationException
     * @throws NoSuchRecordException
     */
    public function generateWebsiteConfiguration(array $website, string $identifier = null): Site
    {
        $rootUid = $this->getWebsiteRootUid($website['uid']);

        $identifier = $identifier ?? $website['config_identifier'];

        return new Site(
            $identifier,
            $rootUid,
            [
                'base' => $website['subdomain'] . '/',
                'baseVariants' => [],
                'errorHandling' => [],
                'flux_content_types' => '',
                'flux_page_templates' => '',
                'languages' => [0 => [
                                        'title' => 'English',
                                        'enabled' => true,
                                        'base' => '/',
                                        'typo3Language' => 'default',
                                        'locale' => 'en_US.UTF-8',
                                        'iso-639-1' => 'en',
                                        'navigationTitle' => '',
                                        'hreflang' => '',
                                        'direction' => 'ltr',
                                        'flag' => 'global',
                                        'languageId' => '0',
                                     ],
                              ],
                'rootPageId' => $rootUid,
                'routes' => [],
            ]
        );
    }

    /**
     * Try to retrieve the website matching the given Uri and return the given website
     *
     * @param \Psr\Http\Message\UriInterface $uri
     * @param bool $devMode
     * @return Site
     * @throws NoSuchWebsiteException
     */
    public function matchUriToWebsite(\Psr\Http\Message\UriInterface $uri): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');

        $path = RouteNormalizer::normalizePath($uri->getPath());
        preg_match("/([\w\-]+)(?:\/.*)?/", $path, $m);

        $website = $queryBuilder
            ->select('*')
            ->from('websites')
            ->where(
                $queryBuilder->expr()->eq('subdomain', $queryBuilder->expr()->literal($m[1]))
            )
            ->execute()
            ->fetch();

        if (!isset($website['uid'])) {
            throw new NoSuchWebsiteException('No website found for this URI: ' . $uri);
        }

        return $website;
    }

    /**
     * @param UriInterface $uri
     * @param bool $devMode
     * @param array|null $website
     * @return int
     * @throws NoSuchWebsiteException
     */
    public function matchUriToPage(array $website, UriInterface $uri): int
    {
        $tail = $uri->getPath();
        $tail = preg_replace("/\/?[\w\-]+\/?(.*)/", "/$1", $tail);

        $q = $this->connectionPool->getQueryBuilderForTable('pages');
        return $q
            ->select('uid')
            ->from('pages')
            ->where($q->expr()->eq('website_uid', $website['uid']))
            ->andWhere($q->expr()->eq('slug', $q->expr()->literal($tail)))
            ->execute()
            ->fetchColumn(0);
    }
}
