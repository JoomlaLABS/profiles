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
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

/** @var \Joomla\CMS\Application\SiteApplication $app */
$app           = Factory::getApplication();
$menuItem      = $app->getMenu()->getActive();
$showCategory   = (int) $this->params->get('show_profile_category', 1) === 1;
$showHeading    = (int) $this->params->get('show_page_heading', 0) === 1;
$pageHeading    = trim((string) $this->params->get('page_heading', ''));
$nameTag        = $showHeading ? 'h2' : 'h1';
$fieldGroups    = $this->fieldGroups;
$containerIdBase = 'profile-groups-' . (int) $this->item->id;

if ($pageHeading === '' && $menuItem) {
	$pageHeading = (string) $menuItem->title;
}

$this->getDocument()->getWebAssetManager()->useScript('bootstrap.tab');
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

	<?php echo $this->item->event->afterDisplayTitle; ?>

	<?php if (!$this->hasDetails) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_JOOMLALABS_PROFILES_PROFILE_NO_DETAILS'); ?></div>
	<?php elseif (!empty($fieldGroups)) : ?>
		<ul class="nav nav-tabs" role="tablist">
			<?php foreach ($fieldGroups as $index => $group) : ?>
				<li class="nav-item" role="presentation">
					<button class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#<?php echo $containerIdBase . '-' . (int) $group['id']; ?>" type="button" role="tab">
						<?php echo $this->escape(Text::_($group['title'])); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="tab-content">
			<?php foreach ($fieldGroups as $index => $group) : ?>
				<div class="tab-pane fade<?php echo $index === 0 ? ' show active' : ''; ?>" id="<?php echo $containerIdBase . '-' . (int) $group['id']; ?>" role="tabpanel">
					<div class="profiles-profile-group-card card">
						<div class="card-body">
							<ul class="fields-container mb-0">
								<?php foreach ($group['fields'] as $field) : ?>
									<?php
									$layout = $field->params->get('layout', 'render');
									$content = trim((string) FieldsHelper::render($field->context, 'field.' . $layout, ['field' => $field]));

									if ($content === '') {
										continue;
									}

									$class = trim($field->name . ' ' . $field->params->get('render_class'));
									?>
									<li class="field-entry <?php echo $this->escape($class); ?>"><?php echo $content; ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

		<?php echo $this->item->event->afterDisplayContent; ?>
</div>