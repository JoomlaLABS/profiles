<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

class ProfilesController extends AdminController
{
    protected $option = 'com_joomlalabs_profiles';

    protected $text_prefix = 'COM_JOOMLALABS_PROFILES';

    public function getModel($name = 'Profile', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
