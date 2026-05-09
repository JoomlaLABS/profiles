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
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class ProfileModel extends ItemModel
{
    private const FIELDS_CONTEXT = 'com_joomlalabs_profiles.record';

    protected $option = 'com_joomlalabs_profiles';

    private ?CMSApplicationInterface $application = null;

    public function setApplication(CMSApplicationInterface $application): void
    {
        $this->application = $application;
    }

    private function getApplication(): CMSApplicationInterface
    {
        return $this->application ?? throw new \RuntimeException('Application not injected into ProfileModel');
    }

    protected function populateState()
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app    = $this->getApplication();
        $params = $app->getParams();

        $this->setState('params', $params);
        $this->setState('filter.language', Multilanguage::isEnabled());

        $rawId = (string) $app->input->getString('id', (string) $params->get('id', ''));
        $id    = (int) strtok($rawId, ':');

        $this->setState('profile.id', $id);
    }

    public function getItem($pk = null)
    {
        $pk = $pk ?: (int) $this->getState('profile.id');

        if ($pk <= 0) {
            return null;
        }

        $db         = $this->getDatabase();
        $user       = $this->getCurrentUser();
        $viewLevels = array_values(array_unique(array_map('intval', $user->getAuthorisedViewLevels())));
        $extension  = 'com_joomlalabs_profiles';
        $query      = $db->createQuery()
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.display_name'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.language'),
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.alias', 'category_alias'),
            ])
            ->from($db->quoteName('#__joomlalabs_profiles', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.id') . ' = :profile_id')
            ->where($db->quoteName('a.published') . ' = 1')
            ->where($db->quoteName('c.extension') . ' = :extension')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('a.access') . ' IN (' . implode(',', $viewLevels) . ')')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', $viewLevels) . ')')
            ->where("JSON_UNQUOTE(JSON_EXTRACT(" . $db->quoteName('c.params') . ", '$.is_public_directory')) = '1'")
            ->bind(':profile_id', $pk, ParameterType::INTEGER)
            ->bind(':extension', $extension);

        if ($this->getState('filter.language')) {
            $languageTag = $this->getApplication()->getLanguage()->getTag();
            $query->whereIn($db->quoteName('a.language'), [$languageTag, '*'], ParameterType::STRING)
                ->whereIn($db->quoteName('c.language'), [$languageTag, '*'], ParameterType::STRING);
        }

        $db->setQuery($query);
        $item = $db->loadObject();

        if ($item) {
            $item->jcfields = FieldsHelper::getFields(self::FIELDS_CONTEXT, $item, true, null, true);
        }

        return $item ?: null;
    }

    public function getGroupedFields(object $item, ?int $displayType = 0): array
    {
        $fields = $item->jcfields ?? [];

        if ($fields === []) {
            return [];
        }

        $groups = [];

        foreach ($fields as $field) {
            if (!$this->matchesDisplayType($field, $displayType)) {
                continue;
            }

            $value = trim((string) ($field->value ?? ''));

            if ($value === '') {
                continue;
            }

            $groupId = (int) ($field->group_id ?? 0);
            $key     = $groupId > 0 ? $groupId : 0;

            if (!isset($groups[$key])) {
                $groupTitle = trim((string) ($field->group_title ?? ''));

                if ($groupTitle === '') {
                    $groupTitle = 'COM_JOOMLALABS_PROFILES_PROFILE_GROUP_DEFAULT';
                }

                $groups[$key] = [
                    'id'          => $key,
                    'title'       => $groupTitle,
                    'description' => '',
                    'fields'      => [],
                ];
            }

            $groups[$key]['fields'][] = $field;
        }

        return array_values($groups);
    }

    public function renderFieldsByDisplay(object $item, int $displayType): string
    {
        $fields = [];

        foreach (($item->jcfields ?? []) as $field) {
            if (!$this->matchesDisplayType($field, $displayType)) {
                continue;
            }

            if (trim((string) ($field->value ?? '')) === '') {
                continue;
            }

            $fields[] = $field;
        }

        if ($fields === []) {
            return '';
        }

        return (string) FieldsHelper::render(
            self::FIELDS_CONTEXT,
            'fields.render',
            [
                'item'    => $item,
                'context' => self::FIELDS_CONTEXT,
                'fields'  => $fields,
            ]
        );
    }

    public function getCategoryPathwayItems(int $categoryId, int $rootCategoryId = 0): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $db        = $this->getDatabase();
        $pathItems = $this->loadCategoryPath($db, $categoryId);

        if ($pathItems === []) {
            return [];
        }

        if ($rootCategoryId > 0) {
            $rootFound = false;

            foreach ($pathItems as $pathItem) {
                if ((int) $pathItem->id === $rootCategoryId) {
                    $rootFound = true;
                    continue;
                }

                if ($rootFound) {
                    $items[] = $pathItem;
                }
            }

            return $items ?? [];
        }

        array_shift($pathItems);

        return $pathItems;
    }

    private function loadCategoryPath(DatabaseInterface $db, int $categoryId): array
    {
        $extension = 'com_joomlalabs_profiles';
        $query     = $db->createQuery()
            ->select([
                $db->quoteName('parent.id'),
                $db->quoteName('parent.title'),
            ])
            ->from($db->quoteName('#__categories', 'node'))
            ->join(
                'INNER',
                $db->quoteName('#__categories', 'parent')
                . ' ON ' . $db->quoteName('parent.lft') . ' <= ' . $db->quoteName('node.lft')
                . ' AND ' . $db->quoteName('parent.rgt') . ' >= ' . $db->quoteName('node.rgt')
            )
            ->where($db->quoteName('node.id') . ' = :category_id')
            ->where($db->quoteName('parent.extension') . ' = :extension')
            ->where($db->quoteName('parent.published') . ' = 1')
            ->order($db->quoteName('parent.lft') . ' ASC')
            ->bind(':category_id', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    private function matchesDisplayType(object $field, ?int $displayType): bool
    {
        if ($displayType === null) {
            return true;
        }

        $params = $field->params ?? null;

        if (!$params instanceof Registry && !\is_object($params)) {
            $params = new Registry($params);
        }

        return (string) $params->get('display', '2') === (string) $displayType;
    }
}
