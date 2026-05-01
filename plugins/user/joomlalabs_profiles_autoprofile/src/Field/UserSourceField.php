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

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

final class UserSourceField extends ListField
{
    protected $type = 'UserSource';

    protected function getOptions()
    {
        $options = parent::getOptions();

        $options[] = HTMLHelper::_('select.option', '{name}', '{name}');
        $options[] = HTMLHelper::_('select.option', '{username}', '{username}');
        $options[] = HTMLHelper::_('select.option', '{email}', '{email}');
        $options[] = HTMLHelper::_('select.option', '{login_name}', '{login_name}');

        $db      = $this->getDatabase();
        $context = 'com_users.user';
        $state   = 1;
        $query   = $db->createQuery()
            ->select([$db->quoteName('f.name'), $db->quoteName('f.title')])
            ->from($db->quoteName('#__fields', 'f'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->bind(':context', $context)
            ->where($db->quoteName('f.state') . ' = :state')
            ->bind(':state', $state, ParameterType::INTEGER)
            ->order($db->quoteName('f.title') . ' ASC');

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

            $options[] = HTMLHelper::_('select.option', 'usercf:' . $name, $text);
        }

        return $options;
    }
}
