<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model;

use Pimcore\Cache\Runtime;
use Pimcore\Logger;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @method \Pimcore\Model\Site\Dao getDao()
 * @method void delete()
 * @method void save()
 */
final class Site extends AbstractModel
{
    /**
     * @var Site|null
     */
    protected static ?Site $currentSite = null;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var array
     */
    protected $domains;

    /**
     * Contains the ID to the Root-Document
     *
     * @var int
     */
    protected $rootId;

    /**
     * @var Document\Page|null
     */
    protected ?Document\Page $rootDocument = null;

    /**
     * @var string
     */
    protected $rootPath;

    /**
     * @var string
     */
    protected $mainDomain = '';

    /**
     * @var string
     */
    protected $errorDocument = '';

    /**
     * @var array
     */
    protected $localizedErrorDocuments;

    /**
     * @var bool
     */
    protected $redirectToMainDomain = false;

    /**
     * @var int|null
     */
    protected $creationDate;

    /**
     * @var int|null
     */
    protected $modificationDate;

    /**
     * @param int $id
     *
     * @return Site|null
     */
    public static function getById($id)
    {
        $cacheKey = 'site_id_'. $id;

        if (Runtime::isRegistered($cacheKey)) {
            $site = Runtime::get($cacheKey);
        } elseif (!$site = \Pimcore\Cache::load($cacheKey)) {
            try {
                $site = new self();
                $site->getDao()->getById((int)$id);
            } catch (NotFoundException $e) {
                $site = 'failed';
            }

            \Pimcore\Cache::save($site, $cacheKey, ['system', 'site'], null, 999);
        }

        if ($site === 'failed' || !$site) {
            $site = null;
        }

        Runtime::set($cacheKey, $site);

        return $site;
    }

    /**
     * @param int $id
     *
     * @return Site|null
     */
    public static function getByRootId($id)
    {
        try {
            $site = new self();
            $site->getDao()->getByRootId((int)$id);

            return $site;
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $domain
     *
     * @return Site|null
     */
    public static function getByDomain($domain)
    {
        // cached because this is called in the route
        $cacheKey = 'site_domain_'. md5($domain);

        if (Runtime::isRegistered($cacheKey)) {
            $site = Runtime::get($cacheKey);
        } elseif (!$site = \Pimcore\Cache::load($cacheKey)) {
            try {
                $site = new self();
                $site->getDao()->getByDomain($domain);
            } catch (NotFoundException $e) {
                $site = 'failed';
            }

            \Pimcore\Cache::save($site, $cacheKey, ['system', 'site'], null, 999);
        }

        if ($site === 'failed' || !$site) {
            $site = null;
        }

        Runtime::set($cacheKey, $site);

        return $site;
    }

    /**
     * @param mixed $mixed
     *
     * @return Site|null
     */
    public static function getBy($mixed)
    {
        $site = null;

        if (is_numeric($mixed)) {
            $site = self::getById($mixed);
        } elseif (is_string($mixed)) {
            $site = self::getByDomain($mixed);
        } elseif ($mixed instanceof Site) {
            $site = $mixed;
        }

        return $site;
    }

    /**
     * @param array $data
     *
     * @return Site
     */
    public static function create($data)
    {
        $site = new self();
        self::checkCreateData($data);
        $site->setValues($data);

        return $site;
    }

    /**
     * returns true if the current process/request is inside a site
     *
     * @return bool
     */
    public static function isSiteRequest()
    {
        if (null !== self::$currentSite) {
            return true;
        }

        return false;
    }

    /**
     * @return Site
     *
     * @throws \Exception
     */
    public static function getCurrentSite()
    {
        if (null !== self::$currentSite) {
            return self::$currentSite;
        }

        throw new \Exception('This request/process is not inside a subsite');
    }

    /**
     * Register the current site
     *
     * @param Site $site
     */
    public static function setCurrentSite(Site $site): void
    {
        self::$currentSite = $site;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * @return int
     */
    public function getRootId()
    {
        return $this->rootId;
    }

    /**
     * @return Document\Page|null
     */
    public function getRootDocument(): ?Document\Page
    {
        return $this->rootDocument;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = (int) $id;

        return $this;
    }

    /**
     * @param mixed $domains
     *
     * @return $this
     */
    public function setDomains($domains)
    {
        if (is_string($domains)) {
            $domains = \Pimcore\Tool\Serialize::unserialize($domains);
        }
        $this->domains = $domains;

        return $this;
    }

    /**
     * @param int $rootId
     *
     * @return $this
     */
    public function setRootId($rootId)
    {
        $this->rootId = (int) $rootId;

        $rd = Document\Page::getById($this->rootId);
        $this->setRootDocument($rd);

        return $this;
    }

    /**
     * @param Document\Page|null $rootDocument
     *
     * @return $this
     */
    public function setRootDocument($rootDocument)
    {
        $this->rootDocument = $rootDocument;

        return $this;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setRootPath($path)
    {
        $this->rootPath = $path;

        return $this;
    }

    /**
     * @return string
     */
    public function getRootPath()
    {
        if (!$this->rootPath && $this->getRootDocument()) {
            return $this->getRootDocument()->getRealFullPath();
        }

        return $this->rootPath;
    }

    /**
     * @param string $errorDocument
     */
    public function setErrorDocument($errorDocument)
    {
        $this->errorDocument = $errorDocument;
    }

    /**
     * @return string
     */
    public function getErrorDocument()
    {
        return $this->errorDocument;
    }

    /**
     * @param mixed $localizedErrorDocuments
     *
     * @return $this
     */
    public function setLocalizedErrorDocuments($localizedErrorDocuments)
    {
        if (is_string($localizedErrorDocuments)) {
            $localizedErrorDocuments = \Pimcore\Tool\Serialize::unserialize($localizedErrorDocuments);
        }
        $this->localizedErrorDocuments = $localizedErrorDocuments;

        return $this;
    }

    /**
     * @return array
     */
    public function getLocalizedErrorDocuments()
    {
        return $this->localizedErrorDocuments;
    }

    /**
     * @param string $mainDomain
     */
    public function setMainDomain($mainDomain)
    {
        $this->mainDomain = $mainDomain;
    }

    /**
     * @return string
     */
    public function getMainDomain()
    {
        return $this->mainDomain;
    }

    /**
     * @param bool $redirectToMainDomain
     */
    public function setRedirectToMainDomain($redirectToMainDomain)
    {
        $this->redirectToMainDomain = (bool) $redirectToMainDomain;
    }

    /**
     * @return bool
     */
    public function getRedirectToMainDomain()
    {
        return $this->redirectToMainDomain;
    }

    /**
     * @internal
     */
    public function clearDependentCache()
    {

        // this is mostly called in Site\Dao not here
        try {
            \Pimcore\Cache::clearTag('site');
        } catch (\Exception $e) {
            Logger::crit((string) $e);
        }
    }

    /**
     * @param int $modificationDate
     *
     * @return $this
     */
    public function setModificationDate($modificationDate)
    {
        $this->modificationDate = (int) $modificationDate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param int $creationDate
     *
     * @return $this
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = (int) $creationDate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }
}
