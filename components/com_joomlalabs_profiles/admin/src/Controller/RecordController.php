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

\defined('_JEXEC') or die;

/**
 * Alias controller used by com_contenthistory when type alias is "record".
 */
class RecordController extends ProfileController
{
    protected $context = 'profile';

    protected $view_item = 'profile';

    protected $view_list = 'profiles';

    public function getModel($name = '', $prefix = '', $config = ['ignore_request' => true])
    {
        if ($name === '') {
            $name = 'Profile';
        }

        return parent::getModel($name, $prefix, $config);
    }
}
