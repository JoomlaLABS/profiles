<?php

declare(strict_types=1);

/**
 * @package     Joomla.Site
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Site\Model;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class DirectoryModel extends ListModel
{
    private const FIELDS_CONTEXT = 'com_joomlalabs_profiles.record';

    protected $option = 'com_joomlalabs_profiles';

    private ?CMSApplicationInterface $application = null;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'display_name', 'a.display_name',
                'ordering', 'a.ordering',
                'created', 'a.created',
                'category_title', 'c.title',
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
        return $this->application ?? throw new \RuntimeException('Application not injected into DirectoryModel');
    }

    protected function populateState($ordering = 'a.display_name', $direction = 'ASC')
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app    = $this->getApplication();
        $params = $app->getParams();

        $this->setState('params', $params);
        $this->setState('filter.search', trim((string) $app->input->getString('filter_search', '')));
        $this->setState('filter.category_id', $app->input->getInt('filter_category_id', 0));
        $this->setState('filter.language', Multilanguage::isEnabled());

        parent::populateState($ordering, $direction);
    }

    private function getRootCategoryId(): int
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app            = $this->getApplication();
        $inputValue     = $app->input->getInt('root_category_id', 0);
        $activeMenu     = $app->getMenu()->getActive();
        $menuQueryValue = (int) (($activeMenu->query['root_category_id'] ?? 0));
        $paramsValue    = (int) $this->getState('params')->get('root_category_id', 0);

        return $inputValue > 0 ? $inputValue : ($menuQueryValue > 0 ? $menuQueryValue : $paramsValue);
    }

    public function getItems()
    {
        $items = parent::getItems();

        if ($items === false) {
            return false;
        }

        $teaserFields = $this->getConfiguredTeaserFields();

        if ($teaserFields === []) {
            return $items;
        }

        foreach ($items as $item) {
            $item->teasers = $this->loadTeasersForItem($item, $teaserFields);
        }

        return $items;
    }

    public function getAvailableCategories(): array
    {
        $db              = $this->getDatabase();
        $user            = $this->getCurrentUser();
        $viewLevels      = array_values(array_unique(array_map('intval', $user->getAuthorisedViewLevels())));
        $rootCategoryId  = $this->getRootCategoryId();
        $includeChildren = (int) $this->getState('params')->get('include_subcategories', 1) === 1;
        $query           = $db->createQuery()
            ->select([
                $db->quoteName('c.id'),
                $db->quoteName('c.title'),
            ])
            ->from($db->quoteName('#__categories', 'c'))
            ->where($db->quoteName('c.extension') . ' = :extension')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', $viewLevels) . ')')
            ->where("JSON_UNQUOTE(JSON_EXTRACT(" . $db->quoteName('c.params') . ", '$.is_public_directory')) = '1'")
            ->order($db->quoteName('c.lft') . ' ASC');

        $extension = 'com_joomlalabs_profiles';
        $query->bind(':extension', $extension);

        if ($this->getState('filter.language')) {
            $languageTag = $this->getApplication()->getLanguage()->getTag();
            $query->whereIn($db->quoteName('c.language'), [$languageTag, '*'], ParameterType::STRING);
        }

        if ($rootCategoryId > 0) {
            $rootCategory = $this->loadCategoryBounds($rootCategoryId);

            if ($rootCategory) {
                if ($includeChildren) {
                    $query->where($db->quoteName('c.lft') . ' >= ' . (int) $rootCategory->lft)
                        ->where($db->quoteName('c.rgt') . ' <= ' . (int) $rootCategory->rgt);
                } else {
                    $query->where($db->quoteName('c.id') . ' = :root_category_id')
                        ->bind(':root_category_id', $rootCategoryId, ParameterType::INTEGER);
                }
            }
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getConfiguredTeaserFields(): array
    {
        $raw = $this->getState('params')->get('teaser_fields', []);

        if (\is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (\is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!\is_array($raw)) {
            return [];
        }

        $fields = [];

        foreach ($raw as $row) {
            if ($row instanceof Registry) {
                $row = $row->toArray();
            }

            if (\is_object($row)) {
                $row = (array) $row;
            }

            if (!\is_array($row)) {
                continue;
            }

            $fieldName = trim((string) ($row['field_name'] ?? ''));

            if ($fieldName !== '' && !\in_array($fieldName, $fields, true)) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }

    protected function getListQuery(): QueryInterface
    {
        $db              = $this->getDatabase();
        $user            = $this->getCurrentUser();
        $viewLevels      = array_values(array_unique(array_map('intval', $user->getAuthorisedViewLevels())));
        $rootCategoryId  = $this->getRootCategoryId();
        $includeChildren = (int) $this->getState('params')->get('include_subcategories', 1) === 1;
        $selectedCatId   = (int) $this->getState('filter.category_id');
        $extension       = 'com_joomlalabs_profiles';
        $query           = $db->createQuery();

        $slug = "CASE WHEN CHAR_LENGTH(" . $db->quoteName('a.alias') . ") THEN CONCAT_WS(':', " . $db->quoteName('a.id') . ", " . $db->quoteName('a.alias') . ") ELSE " . $db->quoteName('a.id') . " END";

        $query->select([
            $db->quoteName('a.id'),
            $db->quoteName('a.display_name'),
            $db->quoteName('a.alias'),
            $db->quoteName('a.catid'),
            $db->quoteName('a.language'),
            $db->quoteName('c.title', 'category_title'),
            $slug . ' AS ' . $db->quoteName('slug'),
        ])
            ->from($db->quoteName('#__joomlalabs_profiles', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.published') . ' = 1')
            ->where($db->quoteName('c.extension') . ' = :extension')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('a.access') . ' IN (' . implode(',', $viewLevels) . ')')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', $viewLevels) . ')')
            ->where("JSON_UNQUOTE(JSON_EXTRACT(" . $db->quoteName('c.params') . ", '$.is_public_directory')) = '1'")
            ->bind(':extension', $extension);

        if ($this->getState('filter.language')) {
            $languageTag = $this->getApplication()->getLanguage()->getTag();
            $query->whereIn($db->quoteName('a.language'), [$languageTag, '*'], ParameterType::STRING)
                ->whereIn($db->quoteName('c.language'), [$languageTag, '*'], ParameterType::STRING);
        }

        if ($selectedCatId > 0) {
            $selectedCategory = $this->loadCategoryBounds($selectedCatId);

            if ($selectedCategory) {
                $query->where($db->quoteName('c.lft') . ' >= ' . (int) $selectedCategory->lft)
                    ->where($db->quoteName('c.rgt') . ' <= ' . (int) $selectedCategory->rgt);
            }
        } elseif ($rootCategoryId > 0) {
            $rootCategory = $this->loadCategoryBounds($rootCategoryId);

            if ($rootCategory) {
                if ($includeChildren) {
                    $query->where($db->quoteName('c.lft') . ' >= ' . (int) $rootCategory->lft)
                        ->where($db->quoteName('c.rgt') . ' <= ' . (int) $rootCategory->rgt);
                } else {
                    $query->where($db->quoteName('c.id') . ' = :root_category_id')
                        ->bind(':root_category_id', $rootCategoryId, ParameterType::INTEGER);
                }
            }
        }

        $search = $this->getState('filter.search');

        if ($search !== '') {
            $token = '%' . $search . '%';
            $query->where($db->quoteName('a.display_name') . ' LIKE :search')
                ->bind(':search', $token);
        }

        $query->order($db->quoteName('a.display_name') . ' ASC');

        return $query;
    }

    private function loadCategoryBounds(int $categoryId): ?object
    {
        if ($categoryId <= 0) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([
                $db->quoteName('id'),
                $db->quoteName('lft'),
                $db->quoteName('rgt'),
            ])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :category_id')
            ->bind(':category_id', $categoryId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    private function loadTeasersForItem(object $item, array $teaserFields): array
    {
        $available = FieldsHelper::getFields(self::FIELDS_CONTEXT, $item, true, null, true);
        $indexed   = [];

        foreach ($available as $field) {
            $indexed[(string) ($field->name ?? '')] = $field;
        }

        $teasers = [];

        foreach ($teaserFields as $fieldName) {
            $field = $indexed[$fieldName] ?? null;

            if (!$field) {
                continue;
            }

            $value = trim((string) ($field->value ?? ''));

            if ($value === '') {
                continue;
            }

            $teasers[] = [
                'label' => (string) ($field->label ?: $field->title ?: $fieldName),
                'value' => $value,
            ];
        }

        return $teasers;
    }
}
