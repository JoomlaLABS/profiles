<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Model;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Category;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

class ProfilesModel extends ListModel
{
    protected $option = 'com_joomlalabs_profiles';

    private ?CMSApplicationInterface $application = null;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'display_name', 'a.display_name',
                'alias', 'a.alias',
                'published', 'a.published',
                'catid', 'a.catid', 'category_id', 'category_title',
                'user_id', 'a.user_id', 'linked_user_name', 'u.name',
                'access', 'a.access', 'access_level',
                'language', 'a.language', 'language_title',
                'ordering', 'a.ordering',
                'created', 'a.created',
            ];
        }

        parent::__construct($config, $factory);
    }

    public function setApplication(CMSApplicationInterface $application): void
    {
        $this->application = $application;
    }

    private function getApplication(): CMSApplicationInterface
    {
        return $this->application ?? throw new \RuntimeException('Application not injected into ProfilesModel');
    }

    protected function populateState($ordering = 'a.display_name', $direction = 'asc')
    {
        $app = $this->getApplication();
        $layout = (string) $app->getInput()->get('layout');

        if ($layout !== '') {
            $this->context .= '.' . $layout;
        }

        parent::populateState($ordering, $direction);

        if ($layout === 'modal') {
            $this->setState('filter.published', '1');
        }

        // Treat 0/empty values as "no filter" so Search Tools does not mark dropdown filters as active.
        $this->normalizeCategoryFilterState();
        $this->normalizeIntegerFilterState('filter.linked_user');
        $this->normalizeIntegerFilterState('filter.access');
    }

    private function normalizeCategoryFilterState(): void
    {
        $categoryIds = $this->state->get('filter.category_id', []);

        if (!\is_array($categoryIds)) {
            $categoryIds = $categoryIds !== '' && $categoryIds !== null ? [$categoryIds] : [];
        }

        $categoryIds = ArrayHelper::toInteger($categoryIds);
        $categoryIds = array_values(array_filter($categoryIds, static fn (int $categoryId): bool => $categoryId > 0));

        $this->setState('filter.category_id', $categoryIds);
    }

    private function normalizeIntegerFilterState(string $stateKey): void
    {
        $value = $this->state->get($stateKey);

        if (\is_array($value)) {
            $value = reset($value);
        }

        $value = is_numeric($value) ? (int) $value : 0;

        $this->setState($stateKey, $value > 0 ? $value : '');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . serialize($this->getState('filter.category_id'));
        $id .= ':' . $this->getState('filter.linked_user');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.language');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();
        $user  = $this->getCurrentUser();

        $query->select(
            [
                $db->quoteName('a.id'),
                $db->quoteName('a.display_name'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.user_id'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.access'),
                $db->quoteName('a.language'),
                $db->quoteName('a.published'),
                $db->quoteName('a.ordering'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('a.created'),
                $db->quoteName('a.created_by'),
            ]
        )
            ->from($db->quoteName('#__joomlalabs_profiles', 'a'));

        $query->select($db->quoteName('c.title', 'category_title'))
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
            );

        $query->select($db->quoteName('ag.title', 'access_level'))
            ->join(
                'LEFT',
                $db->quoteName('#__viewlevels', 'ag') . ' ON ' . $db->quoteName('ag.id') . ' = ' . $db->quoteName('a.access')
            );

        $query->select($db->quoteName('u.name', 'linked_user_name'))
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id')
            );

        $query->select($db->quoteName('uc.name', 'editor'))
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out')
            );

        $query->select($db->quoteName('l.title', 'language_title'))
            ->select($db->quoteName('l.image', 'language_image'))
            ->join(
                'LEFT',
                $db->quoteName('#__languages', 'l') . ' ON ' . $db->quoteName('l.lang_code') . ' = ' . $db->quoteName('a.language')
            );

        if (!$user->authorise('core.admin')) {
            $query->whereIn($db->quoteName('a.access'), $user->getAuthorisedViewLevels());
        }

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $publishedValue = (int) $published;

            $query->where($db->quoteName('a.published') . ' = :published')
                ->bind(':published', $publishedValue, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->whereIn($db->quoteName('a.published'), [0, 1]);
        }

        $categoryIds = $this->getState('filter.category_id', []);

        if (!\is_array($categoryIds)) {
            $categoryIds = $categoryIds ? [$categoryIds] : [];
        }

        if (\count($categoryIds) > 0) {
            $categoryIds    = ArrayHelper::toInteger($categoryIds);
            $categoryTable  = new Category($db);
            $categoryWheres = [];

            foreach ($categoryIds as $filterCategoryId) {
                if ($filterCategoryId <= 0) {
                    continue;
                }

                $categoryTable->load($filterCategoryId);

                if ((int) $categoryTable->id <= 0) {
                    continue;
                }

                $categoryWheres[] = '('
                    . $db->quoteName('c.lft') . ' >= ' . (int) $categoryTable->lft . ' AND '
                    . $db->quoteName('c.rgt') . ' <= ' . (int) $categoryTable->rgt
                    . ')';
            }

            if ($categoryWheres !== []) {
                $query->where('(' . implode(' OR ', $categoryWheres) . ')');
            }
        }

        $linkedUser = (int) $this->getState('filter.linked_user');

        if ($linkedUser > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :linkedUser')
                ->bind(':linkedUser', $linkedUser, ParameterType::INTEGER);
        }

        $access = (int) $this->getState('filter.access');

        if ($access > 0) {
            $query->where($db->quoteName('a.access') . ' = :access')
                ->bind(':access', $access, ParameterType::INTEGER);
        }

        $language = (string) $this->getState('filter.language');

        if ($language !== '') {
            $query->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $token = '%' . $search . '%';

                $query->where(
                    '(' . $db->quoteName('a.display_name') . ' LIKE :token'
                    . ' OR ' . $db->quoteName('a.alias') . ' LIKE :token)'
                )
                    ->bind(':token', $token);
            }
        }

        $orderCol  = $this->state->get('list.ordering', 'a.display_name');
        $orderDirn = strtoupper($this->state->get('list.direction', 'ASC'));
        $orderDirn = \in_array($orderDirn, ['ASC', 'DESC'], true) ? $orderDirn : 'ASC';

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }
}
