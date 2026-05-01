<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  User.joomlalabs_profiles_autoprofile
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Plugin\User\ProfilesAutoProfile\Field;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class ProfileDestinationField extends ListField
{
    protected $type = 'ProfileDestination';

    protected function getOptions()
    {
        $options    = parent::getOptions();
        $categoryId = $this->resolveSelectedCategoryId();
        $context    = 'com_joomlalabs_profiles.record';

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([$db->quoteName('f.name'), $db->quoteName('f.title')])
            ->from($db->quoteName('#__fields', 'f'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->bind(':context', $context)
            ->where($db->quoteName('f.state') . ' = 1')
            ->order($db->quoteName('f.title') . ' ASC');

        if ($categoryId > 0) {
            $query
                ->join(
                    'INNER',
                    $db->quoteName('#__fields_categories', 'fc')
                    . ' ON ' . $db->quoteName('fc.field_id') . ' = ' . $db->quoteName('f.id')
                )
                ->where($db->quoteName('fc.category_id') . ' = :category_id')
                ->bind(':category_id', $categoryId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException) {
            return $options;
        }

        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));

            if ($name === '') {
                continue;
            }

            $title = trim((string) ($row->title ?? $name));
            $text  = $title . ' [' . $name . ']';

            $options[] = HTMLHelper::_('select.option', $name, $text);
        }

        return $options;
    }

    private function resolveSelectedCategoryId(): int
    {
        /** @var CMSApplication $app */
        $app   = Factory::getApplication();
        $input = $app->getInput()->get('jform', [], 'array');

        $fromInput = (int) ($input['params']['default_category_id'] ?? 0);

        if ($fromInput > 0) {
            return $fromInput;
        }

        $extensionId = $this->resolveCurrentExtensionId($input);
        $stateData   = $app->getUserState('com_plugins.edit.plugin.data', []);
        $stateExtId  = (int) ($stateData['extension_id'] ?? 0);

        if ($extensionId > 0 && $stateExtId === $extensionId) {
            $fromState = (int) ($stateData['params']['default_category_id'] ?? 0);

            if ($fromState > 0) {
                return $fromState;
            }
        }

        $fromParams = (int) $this->form->getValue('default_category_id', 'params', 0);

        if ($fromParams > 0) {
            return $fromParams;
        }

        $fromRoot = (int) $this->form->getValue('default_category_id', null, 0);

        if ($fromRoot > 0) {
            return $fromRoot;
        }

        return $this->resolveCategoryIdFromStoredPluginParams($input, $extensionId);
    }

    private function resolveCurrentExtensionId(array $input): int
    {
        /** @var CMSApplication $app */
        $app         = Factory::getApplication();
        $extensionId = (int) ($input['extension_id'] ?? 0);

        if ($extensionId <= 0) {
            $extensionId = $app->getInput()->getInt('extension_id', 0);
        }

        return max(0, $extensionId);
    }

    private function resolveCategoryIdFromStoredPluginParams(array $input, int $extensionId = 0): int
    {
        if ($extensionId <= 0) {
            $extensionId = $this->resolveCurrentExtensionId($input);
        }

        if ($extensionId <= 0) {
            return 0;
        }

        $db    = $this->getDatabase();
        $type  = 'plugin';
        $query = $db->createQuery()
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = :extension_id')
            ->bind(':extension_id', $extensionId, ParameterType::INTEGER)
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':type', $type)
            ->setLimit(1);

        $db->setQuery($query);

        try {
            $paramsText = (string) $db->loadResult();
        } catch (\RuntimeException) {
            return 0;
        }

        if ($paramsText === '') {
            return 0;
        }

        $params = new Registry($paramsText);

        return max(0, (int) $params->get('default_category_id', 0));
    }
}
