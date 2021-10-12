<?php

namespace Opentalent\Populate\Controller;

use Opentalent\Websites\Exception\NoSuchRecordException;
use Opentalent\Websites\Exception\NoSuchWebsiteException;
use Opentalent\Websites\Controller\ActionController;
use Opentalent\Websites\Website\WebsiteRepository;
use PDO;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class SiteController extends ActionController
{
    // Pages dokType values
    const DOK_PAGE = 1;
    const DOK_SHORTCUT = 4;
    const DOK_FOLDER = 116;

    // Contents CTypes
    const CTYPE_TEXT = 'text';
    const CTYPE_IMAGE = 'image';
    const CTYPE_TEXTPIC = 'textpic';
    const CTYPE_TEXTMEDIA = 'textmedia';
    const CTYPE_HTML = 'html';
    const CTYPE_HEADER = 'header';
    const CTYPE_UPLOADS = 'uploads';
    const CTYPE_LIST = 'list';
    const CTYPE_SITEMAP = 'menu_sitemap';

    // access permissions
    const PERM_SHOW = 1;
    const PERM_EDIT_CONTENT = 16;
    const PERM_EDIT_PAGE = 2;
    const PERM_DELETE = 4;
    const PERM_NEW = 8;

    // Creation mode
    const MODE_PROD = 1;
    const MODE_DEV = 1;

    /**
     * @var \TYPO3\CMS\Core\Database\ConnectionPool
     */
    private \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool;

    public function injectConnectionPool(\TYPO3\CMS\Core\Database\ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @var WebsiteRepository
     */
    protected WebsiteRepository $websiteRepository;

    public function injectWebsiteRepository(WebsiteRepository $websiteRepository) {
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Index of the pages created during the process
     * >> [slug => uid]
     * @var array
     */
    private array $createdPagesIndex;

    /**
     * List of the directories created in the process (for rollback purposes)
     * @var array
     */
    private array $createdDirs;

    /**
     * List of the files created in the process (for rollback purposes)
     * @var array
     */
    private array $createdFiles;

    protected function typo3_install_dir() {
        return rtrim(dirname(__FILE__, 6), '/');
    }

    /**
     * Creates a new website and returns the root page uid of the newly created site
     *
     * @return int Uid of the root page of the newly created website
     * @throws \RuntimeException|\Throwable
     */
    public function createSiteAction(): int
    {
        $this->createdPagesIndex = [];
        $this->createdDirs = [];
        $this->createdFiles = [];

        // ** Create the new website

        // start transactions
        $this->connectionPool->getConnectionByName('Default')->beginTransaction();

        // keep tracks of the created folders and files to be able to remove them during a rollback
        try {
            // Create the website:
            $websiteUid = $this->insertWebsite();

            // Create the site pages:
            // > Root page
            $rootUid = $this->insertRootPage($websiteUid, 'Website ' . $websiteUid);

            $this->insertContent(
                $rootUid,
                self::CTYPE_TEXTPIC,
                '<h1>Welcome on website ' . $websiteUid . ' root page.</h1>',
                0
            );

            // New pages
            foreach (range(0, 4) as $i) {
                $pageUid = $this->insertPage(
                    $websiteUid,
                    $rootUid,
                    'Page ' . $i,
                    '/page-' . $i
                );
                $this->insertContent(
                    $pageUid,
                    self::CTYPE_TEXT,
                    'This is some content',
                    0
                );

                foreach (range(0, 4) as $j) {
                    $subPageUid = $this->insertPage(
                        $websiteUid,
                        $pageUid,
                        'Page ' . $i . '-' . $j,
                        '/page-' . $i . '-' . $j
                    );
                    $this->insertContent(
                        $subPageUid,
                        self::CTYPE_TEXT,
                        'This is some content',
                        0
                    );
                }
            }

            // update sys_template

            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
            $queryBuilder->insert('sys_template')
                ->values([
                    'pid' => $rootUid,
                    'title' => 'Website ' . $websiteUid,
                    'sitetitle' => 'Website ' . $websiteUid,
                    'root' => 1,
                    'clear' => 3,
                    'config' => "page = PAGE\npage.10 = TEXT\npage.10.value = Some page of the website " . $websiteUid
                ])
                ->execute();

            // ## Create the site config.yaml file
            $identifier = $this->writeConfigFile($rootUid);

            // Update the website identifier
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
            $queryBuilder->update('websites')
                ->set('config_identifier', $identifier)
                ->where($queryBuilder->expr()->eq('uid', $websiteUid))
                ->execute();

            // Create the user_upload and form_definitions directories and update the sys_filemounts table
            $uploadRelPath = "/user_upload/" . $websiteUid;
            $fileadminDir = $this->typo3_install_dir() . "/public/fileadmin";
            $uploadDir = $fileadminDir . "/" . $uploadRelPath;
            if (file_exists($uploadDir)) {
                throw new \RuntimeException("A directory or file " . $uploadDir . " already exists. Abort.");
            }

            $formsRelPath = '/form_definitions/' . $websiteUid;
            $formsDir = $fileadminDir . $formsRelPath;
            if (file_exists($formsDir)) {
                throw new \RuntimeException("A directory or file " . $formsDir . " already exists. Abort.");
            }

            $this->mkDir($uploadDir);
            $this->mkDir($formsDir);

            // Insert the filemounts points (sys_filemounts)
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
            $queryBuilder->insert('sys_filemounts')
                ->values([
                    'title' => 'Documents_' . $websiteUid,
                    'path' => rtrim($uploadRelPath, '/') . '/',
                    'base' => 1
                ])
                ->execute();

            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
            $queryBuilder->insert('sys_filemounts')
                ->values([
                    'title' => 'Forms_' . $websiteUid,
                    'path' => rtrim($formsRelPath, '/') . '/',
                    'base' => 1
                ])
                ->execute();

            // Create the BE Editors group
            $beGroupUid = $this->createOrUpdateBeGroup(
                $websiteUid,
                $rootUid
            );

            // Create the BE User
            $beUserUid = $this->createOrUpdateBeUser(
                $websiteUid,
                $rootUid,
                $beGroupUid
            );

            // Update the user TsConfig
            $tsconfig = "options.uploadFieldsInTopOfEB = 1\n" .
                        "options.defaultUploadFolder=1:" . rtrim($uploadRelPath, '/') . "/\n";
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
            $queryBuilder
                ->update('be_users')
                ->where($queryBuilder->expr()->eq('uid', $beUserUid))
                ->set('TSconfig', $tsconfig)
                ->execute();

            // Setup user and group rights
            $this->setBeUserPerms($websiteUid, false, $beGroupUid, $beUserUid);

            // Try to commit the result
            $commitSuccess = $this->connectionPool->getConnectionByName('Default')->commit();
            if (!$commitSuccess) {
                throw new \RuntimeException('Something went wrong while committing the result');
            }

        } catch(\Throwable $e) {
            // rollback
            $this->connectionPool->getConnectionByName('Default')->rollback();

            // remove created files and dirs
            foreach (array_reverse($this->createdFiles) as $filename) {
                unlink($filename);
            }
            $this->createdFiles = [];

            foreach (array_reverse($this->createdDirs) as $dirname) {
                rmdir($dirname);
            }
            $this->createdDirs = [];

            throw $e;
        }

        return $rootUid;
    }

    /**
     * Clear the db of all the sites created with the createSiteAction
     */
    public function clearDbAction() {
        // start transactions
        $this->connectionPool->getConnectionByName('Default')->beginTransaction();

        $trashbin = [];

        // keep tracks of the created folders and files to be able to remove them during a rollback
        try {

            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
            $websites = $queryBuilder
                ->select('*')
                ->from('websites')
                ->execute()
            ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($websites as $website) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
                $pages = $queryBuilder
                    ->select('*')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq('website_uid', $website['uid'])
                    )
                    ->execute()
                    ->fetchAll(PDO::FETCH_ASSOC);

                foreach ($pages as $page) {
                    // delete contents
                    $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
                    $queryBuilder
                        ->delete('tt_content')
                        ->where(
                            $queryBuilder->expr()->eq('pid', $page['uid'])
                        )
                        ->execute();

                    // delete sys_templates
                    $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
                    $queryBuilder
                        ->delete('sys_template')
                        ->where(
                            $queryBuilder->expr()->eq('pid', $page['uid'])
                        )
                        ->execute();

                    if ($page['is_siteroot'] == 1) {
                        // delete groups, users, filemounts
                        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
                        $be_groups = $queryBuilder
                            ->select('*')
                            ->from('be_groups')
                            ->where(
                                $queryBuilder->expr()->eq('db_mountpoints', $page['uid'])
                            )
                            ->execute()
                            ->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($be_groups as $be_group) {

                            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
                            $queryBuilder
                                ->delete('be_users')
                                ->where(
                                    $queryBuilder->expr()->eq('usergroup', $be_group['uid'])
                                )
                                ->execute();

                            foreach (explode(',', $be_groups['file_mountpoints']) as $mountpointUid) {
                                if (is_numeric($mountpointUid)) {
                                    $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
                                    $path = $queryBuilder
                                        ->select('path')
                                        ->from('sys_filemounts')
                                        ->where(
                                            $queryBuilder->expr()->eq('uid', $mountpointUid)
                                        )
                                        ->execute()
                                        ->fetchColumn(0);

                                    $trashbin[] = $this->typo3_install_dir() . $path;

                                    $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
                                    $queryBuilder
                                        ->delete('sys_filemounts')
                                        ->where(
                                            $queryBuilder->expr()->eq('uid', $mountpointUid)
                                        )
                                        ->execute();
                                }
                            }
                        }
                        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
                        $queryBuilder
                            ->delete('be_groups')
                            ->where(
                                $queryBuilder->expr()->eq('db_mountpoints', $page['uid'])
                            )
                            ->execute();
                    }
                }

                // delete pages
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
                $queryBuilder
                    ->delete('pages')
                    ->where(
                        $queryBuilder->expr()->eq('website_uid', $website['uid'])
                    )
                    ->execute();

                // remove site config
                $siteDir = $this->typo3_install_dir() . '/typo3conf/sites/' . $website['config_identifier'];
                $trashbin[] = $siteDir;
            }

            // delete websites
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
            $queryBuilder
                ->delete('websites')
                ->execute();

            foreach ($trashbin as $dirname) {
                $this->rrmdir($dirname);
            }

            // Try to commit the result
            $commitSuccess = $this->connectionPool->getConnectionByName('Default')->commit();
            if (!$commitSuccess) {
                throw new \RuntimeException('Something went wrong while committing the result');
            }
        } catch(\Throwable $e) {
            // rollback
            $this->connectionPool->getConnectionByName('Default')->rollback();
            throw $e;
        }
    }

    /**
     * Insert a new row in the 'websites' table of the Typo3 DB
     * and return its uid
     *
     * @return int
     */
    private function insertWebsite(): int
    {
        $values = [
            'name' => 'temp',
            'subdomain' => 'temp'
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
        $queryBuilder->insert('websites')
            ->values($values)
            ->execute();

        $websiteUid = (int)$queryBuilder->getConnection()->lastInsertId();

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('websites');
        $queryBuilder->update('websites')
            ->set('name', 'Website ' . $websiteUid)
            ->set('subdomain', 'website' . $websiteUid)
            ->execute();

        return $websiteUid;
    }

    /**
     * Insert a new row in the 'pages' table of the Typo3 DB
     * and return its uid
     *
     * @param int $websiteUid
     * @param int $pid
     * @param string $title
     * @param string $slug
     * @param array $moreValues
     * @return int
     */
    private function insertPage(int    $websiteUid,
                                int    $pid,
                                string $title,
                                string $slug,
                                array  $moreValues = []
                                ): int
    {
        $defaultValues = [
            'pid' => $pid,
            'perms_groupid' => 3,
            'perms_user' => 27,
            'cruser_id' => 1,
            'dokType' => self::DOK_PAGE,
            'title' => $title,
            'slug' => $slug,
            'website_uid' => $websiteUid,
        ];

        $values = array_merge($defaultValues, $moreValues);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->insert('pages')
            ->values($values)
            ->execute();

        $uid = (int)$queryBuilder->getConnection()->lastInsertId();

        $this->createdPagesIndex[$slug] = $uid;
        return $uid;
    }

    /**
     * Insert the root page of a new website
     * and return its uid
     *
     * @param int $websiteUid
     * @param string $title
     * @return int
     */
    private function insertRootPage(int $websiteUid, string $title): int
    {
        return $this->insertPage(
            $websiteUid,
            0,
            $title,
            '/',
            [
                'is_siteroot' => 1,
                'TSconfig' => 'TCAdefaults.pages.website_uid=' . $websiteUid
            ]
        );
    }

    /**
     * Insert a new row in the 'tt_content' table of the Typo3 DB
     *
     * @param int $pid
     * @param string $cType
     * @param string $bodyText
     * @param int $colPos
     * @param array $moreValues
     */
    private function insertContent(int $pid,
                                   string $cType=self::CTYPE_TEXT,
                                   string $bodyText = '',
                                   int $colPos=0,
                                   array $moreValues = []) {
        $defaultValues = [
            'pid' => $pid,
            'cruser_id' => 1,
            'CType' => $cType,
            'colPos' => $colPos,
            'bodyText' => $bodyText
        ];

        $values = array_merge($defaultValues, $moreValues);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->insert('tt_content')
            ->values($values)
            ->execute();
    }

    /**
     * Create the given directory, give its property to the www-data group and
     * record it as a newly created dir (for an eventual rollback)
     *
     * @param string $dirPath
     */
    private function mkDir(string $dirPath) {
        mkdir($dirPath);
        $this->createdDirs[] = $dirPath;
        chgrp($dirPath, 'www-data');
    }

    /**
     * Write the given file with content, give its property to the www-data group and
     * record it as a newly created file (for an eventual rollback)
     *
     * @param string $path
     * @param string $content
     */
    private function writeFile(string $path, string $content) {
        $f = fopen($path, "w");
        try
        {
            fwrite($f, $content);
            $this->createdFiles[] = $path;
            try {
                chgrp($path, 'www-data');
            } catch (\TYPO3\CMS\Core\Error\Exception $e) {
            }
        } finally {
            fclose($f);
        }
    }

    /**
     * Create or update the .../sites/.../config.yaml file of the given site
     * Return the identifier of the created website
     *
     * @param int $rootUid
     * @return string Identifier of the newly created configuration file
     * @throws \Opentalent\Websites\Exception\InvalidWebsiteConfigurationException
     * @throws \Opentalent\Websites\Exception\NoSuchRecordException
     * @throws NoSuchWebsiteException
     */
    private function writeConfigFile(int $rootUid): string
    {
        $website = $this->websiteRepository->getWebsiteByPageUid($rootUid);

        $identifier = $website['subdomain'];
        $configDir = $this->typo3_install_dir() . "/typo3conf/sites/" . $identifier;
        $configFilename = $configDir . "/config.yaml";

        if (file_exists($configFilename)) {
            throw new \RuntimeException("A file named " . $configFilename . " already exists. Abort.");
        }

        $siteConfig = $this->websiteRepository->generateWebsiteConfiguration($website, $identifier);
        $config = $siteConfig->getConfiguration();

        $yamlConfig = Yaml::dump($config, 99, 2);

        if (!file_exists($configDir)) {
            $this->mkDir($configDir);
        }
        $this->writeFile($configFilename, $yamlConfig);

        // Set the owner and mods, in case www-data is not the one who run this command
        // @see https://www.php.net/manual/fr/function.stat.php
        try {
            $stats = stat($this->typo3_install_dir() . '/index.php');
            chown($configFilename, $stats['4']);
            chgrp($configFilename, $stats['5']);
            chmod($configFilename, $stats['2']);
        } catch (\Exception $e) {
        }
        return $identifier;
    }

    /**
     * Create the BE user for the website, then return its uid
     *
     * @param int $websiteUid
     * @param int $rootUid
     * @param int $siteGroupUid
     * @return int The uid of the created be_user
     */
    private function createOrUpdateBeUser(int $websiteUid,
                                          int $rootUid,
                                          int $siteGroupUid): int
    {
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('BE');
        $hashedPassword = $hashInstance->getHashedPassword('some_password123');

        $values = [
            'username' => 'Admin ' . $websiteUid,
            'password' => $hashedPassword,
            'description' => '[Auto-generated] BE Admin for website ' . $websiteUid,
            'deleted' => 0,
            'lang' => 'en',
            'usergroup' => $siteGroupUid,
            'userMods' => null,
            'db_mountpoints' => null, // inherited from the editors group
            'file_mountpoints' => null, // inherited from the editors group
            'options' => 3,  // allow to inherit both db and file mountpoints from groups
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $queryBuilder->insert('be_users')
            ->values($values)
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId();
    }

    /**
     * Create the BE editors group for the website, then return its uid
     *
     * @param int $websiteUid
     * @param int $rootUid
     * @param array $userData
     * @return int The uid of the created be_group
     */
    private function createOrUpdateBeGroup(int $websiteUid,
                                  int $rootUid): int
    {
        $groupName = 'editors_' . $websiteUid;

        // get the existing filemounts
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
        $queryBuilder
            ->select('uid')
            ->from('sys_filemounts')
            ->where("path LIKE '%user_upload/" . $websiteUid . "/'")
            ->orWhere("path LIKE '%form_definitions/" . $websiteUid . "/'");
        $statement = $queryBuilder->execute();
        $rows = $statement->fetchAll(3) ?: [];
        $files = [];
        foreach ($rows as $row) {
            $files[] = $row[0];
        }

        $values = [
            'title' => $groupName,
            'deleted' => 0,
            'db_mountpoints' => $rootUid,
            'file_mountPoints' => join(',', $files),
            'file_permissions' => 'readFolder,writeFolder,addFolder,renameFolder,moveFolder,deleteFolder,readFile,writeFile,addFile,renameFile,replaceFile,moveFile,copyFile,deleteFile',
            'groupMods' => '',   // inherited from the base EditorsGroup
            'pagetypes_select' => '',   // inherited from the base EditorsGroup
            'tables_select' => '',   // inherited from the base EditorsGroup
            'tables_modify' => '',   // inherited from the base EditorsGroup
            'non_exclude_fields' => '',   // inherited from the base EditorsGroup
        ];

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->insert('be_groups')
            ->values($values)
            ->execute();

        return $queryBuilder->getConnection()->lastInsertId();
    }

    /**
     * Set the rights of admin and editors of the website
     * on all the existing pages, including deleted ones
     *
     * @param int $websiteUid
     * @param int|null $editorsGroupUid Force the editors be-group uid
     * @param int|null $adminUid Force the admin be-user uid
     * @return int   The uid of the website root page
     * @throws NoSuchWebsiteException
     * @throws NoSuchRecordException
     */
    protected function setBeUserPerms(
        int $websiteUid,
        int $editorsGroupUid = null,
        int $adminUid = null
    ): int
    {
        $rootUid = $this->websiteRepository->getWebsiteRootUid($websiteUid);

        // setup default owner for the website
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $tsConfig = $queryBuilder->select('TSconfig')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $rootUid))
            ->execute()
            ->fetchColumn(0);

        $tsConfig = trim(preg_replace('/TCEMAIN {[^{]*}/', '', $tsConfig));

        $tsConfig .= "\nTCEMAIN {\n" .
            "  permissions.userid = " . $adminUid ."\n" .
            "  permissions.groupid = " . $editorsGroupUid . "\n" .
            "}";

        $queryBuilder
            ->update('pages')
            ->where($queryBuilder->expr()->eq('uid', $rootUid))
            ->set('TSconfig', $tsConfig)
            ->execute();

        // fetch pages and root page
        $pages = $this->extendedPageRepository->getPageWithSubpages($rootUid);

        $adminPerms = self::PERM_SHOW + self::PERM_EDIT_CONTENT + self::PERM_EDIT_PAGE + self::PERM_NEW;
        $editorsPerms = self::PERM_SHOW + self::PERM_EDIT_CONTENT;

        foreach ($pages as $page) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
            $queryBuilder
                ->update('pages')
                ->where($queryBuilder->expr()->eq('uid', $page['uid']))
                ->set('perms_userid', $adminUid)
                ->set('perms_groupid', $editorsGroupUid)
                ->set('perms_user', $adminPerms)
                ->set('perms_group', $editorsPerms)
                ->set('perms_everybody', 0)
                ->execute();
        }

        return $rootUid;
    }

    /**
     * Recursively remove the target directory (! no rollback available)
     */
    private function rrmdir(string $dir) {
        if (!is_dir($dir) || is_link($dir)) return unlink($dir);
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') continue;
            if (!$this->rrmdir($dir . DIRECTORY_SEPARATOR . $file)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->rrmdir($dir . DIRECTORY_SEPARATOR . $file)) return false;
            };
        }
        return rmdir($dir);
    }
}
