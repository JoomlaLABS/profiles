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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Versioning\VersionableControllerTrait;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

class ProfileController extends FormController
{
    use VersionableControllerTrait;

    protected $option = 'com_joomlalabs_profiles';

    protected $view_list = 'profiles';

    protected function allowAdd($data = [])
    {
        $categoryId = ArrayHelper::getValue($data, 'catid', $this->input->getInt('filter_category_id'), 'int');

        if ($categoryId) {
            return $this->app->getIdentity()->authorise('core.create', $this->option . '.category.' . $categoryId);
        }

        return parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'id')
    {
        $recordId = isset($data[$key]) ? (int) $data[$key] : 0;

        if (!$recordId) {
            return parent::allowEdit($data, $key);
        }

        /** @var \JoomlaLabs\Component\Profiles\Administrator\Model\ProfileModel $model */
        $model = $this->getModel('Profile');
        $item  = $model->getItem($recordId);

        if (empty($item)) {
            return false;
        }

        $user = $this->app->getIdentity();

        $canEditOwn = $user->authorise('core.edit.own', $this->option . '.category.' . (int) $item->catid)
            && (int) $item->created_by === (int) $user->id;

        return $canEditOwn || $user->authorise('core.edit', $this->option . '.category.' . (int) $item->catid);
    }
}
