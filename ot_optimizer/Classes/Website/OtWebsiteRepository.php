<?php

namespace Opentalent\OtOptimizer\Website;

use Opentalent\OtOptimizer\Exception\InvalidWebsiteConfigurationException;
use Opentalent\OtOptimizer\Exception\NoSuchRecordException;
use Opentalent\OtOptimizer\Exception\NoSuchWebsiteException;
use Opentalent\OtOptimizer\Utility\RouteNormalizer;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Repository for the ot_websites table
 */
class OtWebsiteRepository
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
     * Retrieves the current full domain of the given website.
     *
     * @param array $website
     * @return string
     * @throws InvalidWebsiteConfigurationException
     */
    public function resolveWebsiteDomain(array $website): string
    {
        if ($website['custom_domain']) {
            return $website['custom_domain'];
        } else if ($website['subdomain']) {
            return $website['subdomain'] . '.opentalent.fr';
        }
        throw new InvalidWebsiteConfigurationException("No domain defined for website " . $website['uid']);
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
                'base' => $this->resolveWebsiteBaseUri($website),
                'baseVariants' => [0 => [
                                'base' => $website['domain'] . '/',
                                'condition' => 'applicationContext == "Development"',
                                        ],
                                ],
                'errorHandling' => [0 => ['errorCode' => '404',
                                            'errorHandler' => 'PHP',
                                            'errorPhpClassFQCN' => 'Opentalent\\OtTemplating\\Page\\ErrorHandler',
                                         ],
                                    1 => ['errorCode' => '403',
                                            'errorHandler' => 'PHP',
                                            'errorPhpClassFQCN' => 'Opentalent\\OtTemplating\\Page\\ErrorHandler',
                                         ],
                                    ],
                'flux_content_types' => '',
                'flux_page_templates' => '',
                'languages' => [0 => [
                                        'title' => 'Fr',
                                        'enabled' => true,
                                        'base' => '/',
                                        'typo3Language' => 'fr',
                                        'locale' => 'fr_FR',
                                        'iso-639-1' => 'fr',
                                        'navigationTitle' => 'Fr',
                                        'hreflang' => 'fr-FR',
                                        'direction' => 'ltr',
                                        'flag' => 'fr',
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
    public function matchUriToWebsite(\Psr\Http\Message\UriInterface $uri, bool $devMode=false): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('ot_websites');

        $domain = RouteNormalizer::normalizeDomain($uri->getHost());

        $q = $queryBuilder
            ->select('*')
            ->from('ot_websites')
            ->where($queryBuilder->expr()->eq('domain', $queryBuilder->expr()->literal($domain)));

        $website = $q->execute()
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
    public function matchUriToPage(array $otWebsite, UriInterface $uri, bool $devMode=false): int
    {
        $tail = $uri->getPath();
        if ($devMode) {
            $tail = preg_replace("/\/?[\w\-]+\/?(.*)/", "/$1", $tail);
        }
        if ($tail != "/") {
            $tail = rtrim($tail, '/');
        }

        $q = $this->connectionPool->getQueryBuilderForTable('pages');
        return $q
            ->select('uid')
            ->from('pages')
            ->where($q->expr()->eq('ot_website_uid', $otWebsite['uid']))
            ->andWhere($q->expr()->eq('slug', $q->expr()->literal($tail)))
            ->execute()
            ->fetchColumn(0);
    }
}
