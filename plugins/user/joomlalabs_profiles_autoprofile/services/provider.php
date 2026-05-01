<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  User.joomlalabs_profiles_autoprofile
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomlaLabs\Plugin\User\ProfilesAutoProfile\Extension\ProfilesAutoProfile;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(ProfilesAutoProfile::class, function (Container $container) {
                $plugin = new ProfilesAutoProfile((array) PluginHelper::getPlugin('user', 'joomlalabs_profiles_autoprofile'));
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            })
        );
    }
};
