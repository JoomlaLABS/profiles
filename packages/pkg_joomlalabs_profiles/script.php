<?php

declare(strict_types=1);

/**
 * @package     Joomla.Package
 * @subpackage  pkg_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class pkg_joomlalabs_profilesInstallerScript
{
    public function postflight($type, $parent)
    {
        if (!\in_array((string) $type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        $this->enablePlugin('actionlog', 'joomlalabs_profiles');
        $this->enablePlugin('privacy', 'joomlalabs_profiles');
    }

    private function enablePlugin(string $folder, string $element): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $enabled = 1;
            $type    = 'plugin';

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = :enabled')
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':enabled', $enabled, ParameterType::INTEGER)
                ->bind(':type', $type)
                ->bind(':folder', $folder)
                ->bind(':element', $element);

            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $exception) {
            Log::add(
                'pkg_joomlalabs_profiles: unable to auto-enable ' . $folder . ' plugin ' . $element . '. ' . $exception->getMessage(),
                Log::WARNING,
                'pkg_joomlalabs_profiles'
            );

            // Do not block installation if plugin auto-enable fails.
        }
    }
}
