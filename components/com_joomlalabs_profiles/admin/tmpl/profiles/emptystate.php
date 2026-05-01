<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

/** @var \JoomlaLabs\Component\Profiles\Administrator\View\Profiles\HtmlView $this */

$displayData = [
    'textPrefix'    => 'COM_JOOMLALABS_PROFILES',
    'formURL'       => 'index.php?option=com_joomlalabs_profiles',
    'icon'          => 'icon-address-book profile',
    'controlFields' => $this->filterForm->renderControlFields(),
];

$user = $this->getCurrentUser();

if ($user->authorise('core.create', 'com_joomlalabs_profiles') || \count($user->getAuthorisedCategories('com_joomlalabs_profiles', 'core.create')) > 0) {
    $displayData['createURL'] = 'index.php?option=com_joomlalabs_profiles&task=profile.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
