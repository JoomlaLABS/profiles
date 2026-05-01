<?php

declare(strict_types=1);

/**
 * @package     Joomla.Site
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Application\SiteApplication $app */
$app          = Factory::getApplication();
$menuItem     = $app->getMenu()->getActive();
$showCategory = (int) $this->params->get('show_profile_category', 1) === 1;
$showHeading  = (int) $this->params->get('show_page_heading', 0) === 1;
$pageHeading  = trim((string) $this->params->get('page_heading', ''));
$nameTag      = $showHeading ? 'h2' : 'h1';
$fieldGroups  = $this->fieldGroups;

if ($pageHeading === '' && $menuItem) {
	$pageHeading = (string) $menuItem->title;
}

$this->getDocument()->addStyleSheet(Uri::root(true) . '/components/com_joomlalabs_profiles/media/css/site.css');
?>

<div class="com-joomlalabs-profiles-profile">
	<?php if ($showHeading && $pageHeading !== '') : ?>
		<div class="page-header">
			<h1><?php echo $this->escape($pageHeading); ?></h1>
		</div>
	<?php endif; ?>

	<header class="profiles-profile-header">
		<<?php echo $nameTag; ?>><?php echo $this->escape($this->item->display_name); ?></<?php echo $nameTag; ?>>
		<?php if ($showCategory) : ?>
			<div class="profiles-profile-category"><?php echo $this->escape($this->item->category_title); ?></div>
		<?php endif; ?>
	</header>

	<?php if (empty($fieldGroups)) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_JOOMLALABS_PROFILES_PROFILE_NO_DETAILS'); ?></div>
	<?php else : ?>
		<?php foreach ($fieldGroups as $group) : ?>
			<section class="profiles-profile-group-card card">
				<div class="card-header"><?php echo $this->escape(Text::_($group['title'])); ?></div>
				<div class="card-body">
					<dl class="profiles-profile-fields mb-0">
						<?php foreach ($group['fields'] as $field) : ?>
							<dt><?php echo $this->escape(Text::_($field->label ?: $field->title)); ?></dt>
							<dd><?php echo $field->value; ?></dd>
						<?php endforeach; ?>
					</dl>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>