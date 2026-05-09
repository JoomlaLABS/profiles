<?php

declare(strict_types=1);

/**
 * @package     Joomla.Site
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\PreprocessRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

class Router extends RouterView
{
    private DatabaseInterface $db;

    private array $categoryPathCache = [];

    private array $categoryChildCache = [];

    private array $profileCache = [];

    public function __construct(SiteApplication $app, AbstractMenu $menu, ?CategoryFactoryInterface $categoryFactory = null, ?DatabaseInterface $db = null)
    {
        if (!$db) {
            throw new \RuntimeException('Database service not available for com_joomlalabs_profiles router.');
        }

        $this->db   = $db;
        $this->name = 'joomlalabs_profiles';

        $directory = new RouterViewConfiguration('directory');
        $this->registerView($directory);

        $category = new RouterViewConfiguration('category');
        $category->setKey('id')
            ->setParent($directory)
            ->setNestable();
        $this->registerView($category);

        $profile = new RouterViewConfiguration('profile');
        $profile->setKey('id')
            ->setParent($category, 'catid')
            ->addLayout('cards')
            ->addLayout('card_deck_2_columns')
            ->addLayout('tabs');
        $this->registerView($profile);

        parent::__construct($app, $menu);

        $preprocess = new PreprocessRules($profile, '#__joomlalabs_profiles', 'id', 'catid');
        $preprocess->setDatabase($this->db);
        $this->attachRule($preprocess);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    public function build(&$query)
    {
        if ($this->buildDirectoryMenuRoute($query)) {
            return [];
        }

        $segments = $this->buildDirectoryProfileRoute($query);

        if ($segments !== null) {
            return $segments;
        }

        return parent::build($query);
    }

    public function parse(&$segments)
    {
        $vars = $this->parseDirectoryProfileRoute($segments);

        if ($vars !== null) {
            return $vars;
        }

        return parent::parse($segments);
    }

    public function preprocess($query)
    {
        if (($query['view'] ?? '') === 'profile' && !empty($query['Itemid'])) {
            $menuItem = $this->menu->getItem((int) $query['Itemid']);

            if ($this->isDirectoryMenuItem($menuItem)) {
                $profile = $this->resolveProfileFromQuery($query['id'] ?? '');

                if ($profile) {
                    if (!str_contains((string) $query['id'], ':') && $profile->alias !== '') {
                        $query['id'] = (int) $profile->id . ':' . $profile->alias;
                    }

                    $query['catid'] = (int) $profile->catid;
                }

                return $query;
            }
        }

        return parent::preprocess($query);
    }

    private function buildDirectoryMenuRoute(array &$query): bool
    {
        if (($query['view'] ?? '') !== 'directory' || empty($query['Itemid'])) {
            return false;
        }

        $menuItem = $this->menu->getItem((int) $query['Itemid']);

        if (!$this->isDirectoryMenuItem($menuItem)) {
            return false;
        }

        unset($query['view'], $query['root_category_id']);

        return true;
    }

    public function getCategorySegment($id, $query): array
    {
        $id = (int) $id;

        if ($id <= 0) {
            return [];
        }

        $path = $this->getCategoryPath($id);

        if ($path === []) {
            return [];
        }

        $rootCategoryId = $this->getConfiguredRootCategoryId($query);

        if ($rootCategoryId > 0) {
            $rootPath = $this->getCategoryPath($rootCategoryId);

            if ($rootPath !== []) {
                $rootIds = array_keys($rootPath);

                foreach ($rootIds as $rootId) {
                    if (isset($path[$rootId])) {
                        unset($path[$rootId]);
                    }
                }
            }
        }

        return $path;
    }

    public function getCategoryId($segment, $query)
    {
        $segment = trim((string) $segment);

        if ($segment === '') {
            return false;
        }

        $parentId = 1;

        if (!empty($query['id'])) {
            $parentId = (int) $query['id'];
        } else {
            $rootCategoryId = $this->getConfiguredRootCategoryId($query);

            if ($rootCategoryId > 0) {
                $parentId = $rootCategoryId;
            }
        }

        $category = $this->getCategoryChildByAlias($parentId, $segment);

        return $category ? (int) $category->id : false;
    }

    public function getProfileSegment($id, $query): array
    {
        $profileId = (int) strtok((string) $id, ':');

        if ($profileId <= 0) {
            return [];
        }

        $profile = $this->getProfile($profileId);

        if (!$profile) {
            return [];
        }

        return [(int) $profile->id => ($profile->alias !== '' ? $profile->alias : (string) $profile->id)];
    }

    public function getProfileId($segment, $query)
    {
        $segment = trim((string) $segment);

        if ($segment === '') {
            return false;
        }

        $categoryId = !empty($query['id']) ? (int) $query['id'] : 0;

        if (is_numeric($segment)) {
            $profile = $this->getProfile((int) $segment);

            if ($profile && ($categoryId === 0 || (int) $profile->catid === $categoryId)) {
                return (int) $profile->id;
            }
        }

        $cacheKey = $categoryId . ':' . $segment;

        if (\array_key_exists($cacheKey, $this->profileCache)) {
            return $this->profileCache[$cacheKey] ?: false;
        }

        if (is_numeric($segment)) {
            $this->profileCache[$cacheKey] = null;

            return false;
        }

        $dbQuery = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__joomlalabs_profiles'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $segment, ParameterType::STRING);

        if ($categoryId > 0) {
            $dbQuery->where($this->db->quoteName('catid') . ' = :catid')
                ->bind(':catid', $categoryId, ParameterType::INTEGER);
        }

        $this->db->setQuery($dbQuery);
        $id = (int) $this->db->loadResult();

        $this->profileCache[$cacheKey] = $id > 0 ? $id : null;

        return $id > 0 ? $id : false;
    }

    private function getConfiguredRootCategoryId(array $query): int
    {
        if (!empty($query['root_category_id'])) {
            return (int) $query['root_category_id'];
        }

        $itemId = !empty($query['Itemid']) ? (int) $query['Itemid'] : 0;

        if ($itemId > 0) {
            $menuItem = $this->menu->getItem($itemId);

            if ($menuItem && !empty($menuItem->query['root_category_id'])) {
                return (int) $menuItem->query['root_category_id'];
            }
        }

        $active = $this->menu->getActive();

        if ($active && !empty($active->query['root_category_id'])) {
            return (int) $active->query['root_category_id'];
        }

        return 0;
    }

    private function buildDirectoryProfileRoute(array &$query): ?array
    {
        if (($query['view'] ?? '') !== 'profile' || empty($query['Itemid'])) {
            return null;
        }

        $menuItem = $this->menu->getItem((int) $query['Itemid']);

        if (!$this->isDirectoryMenuItem($menuItem)) {
            return null;
        }

        $profile = $this->resolveProfileFromQuery($query['id'] ?? '');

        if (!$profile) {
            return null;
        }

        $query['catid'] = (int) $profile->catid;

        $categorySegments = array_values($this->getCategorySegment((int) $profile->catid, $query));
        $segments         = $categorySegments;
        $segments[]       = $profile->alias !== '' ? (string) $profile->alias : (string) $profile->id;

        unset($query['view'], $query['id'], $query['catid']);

        return $segments;
    }

    private function parseDirectoryProfileRoute(array &$segments): ?array
    {
        $active = $this->menu->getActive();

        if (!$this->isDirectoryMenuItem($active) || $segments === []) {
            return null;
        }

        $workingSegments = $segments;
        $profileSegment  = array_pop($workingSegments);
        $rootCategoryId  = !empty($active->query['root_category_id']) ? (int) $active->query['root_category_id'] : 0;
        $currentCategory = $rootCategoryId > 0 ? $rootCategoryId : 1;

        foreach ($workingSegments as $categorySegment) {
            $category = $this->getCategoryChildByAlias($currentCategory, (string) $categorySegment);

            if (!$category) {
                return null;
            }

            $currentCategory = (int) $category->id;
        }

        $profileQuery = [];

        if ($currentCategory > 1) {
            $profileQuery['id'] = $currentCategory;
        }

        $profileId = $this->getProfileId((string) $profileSegment, $profileQuery);

        if (!$profileId && $workingSegments === []) {
            $profileId = $this->getProfileId((string) $profileSegment, []);
        }

        if (!$profileId) {
            return null;
        }

        $segments = [];

        return ['view' => 'profile', 'id' => (int) $profileId];
    }

    private function isDirectoryMenuItem($menuItem): bool
    {
        return \is_object($menuItem)
            && ($menuItem->component ?? '') === 'com_joomlalabs_profiles'
            && ($menuItem->query['view'] ?? '') === 'directory';
    }

    private function getCategoryPath(int $categoryId): array
    {
        if (isset($this->categoryPathCache[$categoryId])) {
            return $this->categoryPathCache[$categoryId];
        }

        $extension = 'com_joomlalabs_profiles';

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('parent.id'),
                $this->db->quoteName('parent.alias'),
            ])
            ->from($this->db->quoteName('#__categories', 'node'))
            ->join(
                'INNER',
                $this->db->quoteName('#__categories', 'parent')
                . ' ON ' . $this->db->quoteName('parent.lft') . ' <= ' . $this->db->quoteName('node.lft')
                . ' AND ' . $this->db->quoteName('parent.rgt') . ' >= ' . $this->db->quoteName('node.rgt')
            )
            ->where($this->db->quoteName('node.id') . ' = :category_id')
            ->where($this->db->quoteName('parent.extension') . ' = :extension')
            ->where($this->db->quoteName('parent.id') . ' > 1')
            ->order($this->db->quoteName('parent.lft') . ' ASC')
            ->bind(':category_id', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];
        $path = [];

        foreach ($rows as $row) {
            $path[(int) $row->id] = $this->normalizeCategorySegment((string) $row->alias);
        }

        $this->categoryPathCache[$categoryId] = $path;

        return $path;
    }

    private function getCategoryChildByAlias(int $parentId, string $alias): ?object
    {
        $cacheKey = $parentId . ':' . $alias;

        if (\array_key_exists($cacheKey, $this->categoryChildCache)) {
            return $this->categoryChildCache[$cacheKey];
        }

        $extension = 'com_joomlalabs_profiles';

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('alias'),
            ])
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('parent_id') . ' = :parent_id')
            ->where($this->db->quoteName('extension') . ' = :extension')
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':parent_id', $parentId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':alias', $alias, ParameterType::STRING);

        $this->db->setQuery($query);
        $category = $this->db->loadObject() ?: null;

        $this->categoryChildCache[$cacheKey] = $category;

        return $category;
    }

    private function getProfile(int $profileId): ?object
    {
        if (\array_key_exists($profileId, $this->profileCache)) {
            return \is_object($this->profileCache[$profileId]) ? $this->profileCache[$profileId] : null;
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('alias'),
                $this->db->quoteName('catid'),
            ])
            ->from($this->db->quoteName('#__joomlalabs_profiles'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $profileId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $profile = $this->db->loadObject() ?: null;

        $this->profileCache[$profileId] = $profile;

        return $profile;
    }

    private function resolveProfileFromQuery($id): ?object
    {
        $rawId     = urldecode((string) $id);
        $profileId = (int) strtok($rawId, ':');

        if ($profileId <= 0) {
            return null;
        }

        return $this->getProfile($profileId);
    }

    private function normalizeCategorySegment(string $alias): string
    {
        return $alias !== '' ? $alias : 'category';
    }
}
