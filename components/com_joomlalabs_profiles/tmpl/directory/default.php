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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Application\SiteApplication $app */
$app           = Factory::getApplication();
$itemId        = $app->input->getInt('Itemid');
$menuItem      = $itemId ? $app->getMenu()->getItem($itemId) : $app->getMenu()->getActive();
$params        = $this->params;
$showFilters   = (int) $params->get('show_directory_filters', 1) === 1;
$showCategory  = (int) $params->get('show_directory_category', 1) === 1;
$showCategoryFilter = !empty($this->showCategoryFilter);
$showHeading   = (int) $params->get('show_page_heading', 0) === 1;
$pageHeading   = trim((string) $params->get('page_heading', ''));
$currentRoute  = Route::_('index.php' . ($itemId ? '?Itemid=' . $itemId : ''));
$categoryOptions = [HTMLHelper::_('select.option', '0', Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_ALL_CATEGORIES'))];

if ($pageHeading === '' && $menuItem) {
	$pageHeading = (string) $menuItem->title;
}

foreach ($this->categories as $category) {
	$categoryOptions[] = HTMLHelper::_('select.option', (string) $category->id, (string) ($category->list_title ?? $category->title));
}

$this->getDocument()->addStyleSheet(Uri::root(true) . '/components/com_joomlalabs_profiles/media/css/site.css');

$profileLinkSuffix = '';

if ($itemId) {
	$profileLinkSuffix .= '&Itemid=' . $itemId;
}
?>

<div class="com-joomlalabs-profiles-directory">
	<?php if ($showHeading && $pageHeading !== '') : ?>
		<div class="page-header">
			<h1><?php echo $this->escape($pageHeading); ?></h1>
		</div>
	<?php endif; ?>

	<?php if ($showFilters) : ?>
		<form action="<?php echo $currentRoute; ?>" method="get" class="card card-body mb-4">
			<?php if ($itemId) : ?>
				<input type="hidden" name="Itemid" value="<?php echo (int) $itemId; ?>">
			<?php endif; ?>
			<div class="row g-3 align-items-end">
				<div class="col-12 <?php echo $showCategoryFilter ? 'col-md-5' : 'col-md-10'; ?>">
					<label class="form-label" for="filter_search"><?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_SEARCH_LABEL'); ?></label>
					<input
						type="search"
						name="filter_search"
						id="filter_search"
						class="form-control"
						value="<?php echo $this->escape($this->filterSearch); ?>"
						placeholder="<?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_SEARCH_PLACEHOLDER'); ?>"
					>
				</div>
				<?php if ($showCategoryFilter) : ?>
					<div class="col-12 col-md-5">
						<label class="form-label" for="filter_category_id"><?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_CATEGORY_LABEL'); ?></label>
						<?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', [
							'id' => 'filter_category_id',
							'name' => 'filter_category_id',
							'options' => $categoryOptions,
							'value' => (string) $this->filterCategoryId,
							'size' => 1,
							'class' => 'w-100',
							'hint' => Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_CATEGORY_LABEL'),
							'label' => Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_CATEGORY_LABEL'),
							'multiple' => false,
							'required' => false,
							'disabled' => false,
							'readonly' => false,
							'autofocus' => false,
							'onchange' => '',
							'dataAttribute' => '',
						]); ?>
					</div>
				<?php endif; ?>
				<div class="col-12 col-md-2 d-grid">
					<button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_FILTER_SUBMIT'); ?></button>
				</div>
			</div>
		</form>
	<?php endif; ?>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info">
			<?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_NO_RESULTS'); ?>
		</div>
	<?php else : ?>
		<div class="profiles-directory-grid">
			<?php foreach ($this->items as $item) : ?>
				<div class="profiles-directory-card card">
					<div class="card-body">
						<h2 class="h4 mb-2">
							<a href="<?php echo Route::_('index.php?option=com_joomlalabs_profiles&view=profile&id=' . (string) $item->slug . $profileLinkSuffix); ?>">
								<?php echo $this->escape($item->display_name); ?>
							</a>
						</h2>

						<?php if ($showCategory) : ?>
							<div class="profiles-directory-meta"><?php echo $this->escape($item->category_title); ?></div>
						<?php endif; ?>

						<?php if (!empty($item->teasers)) : ?>
							<dl class="profiles-directory-teasers mb-0">
								<?php foreach ($item->teasers as $teaser) : ?>
									<dt><?php echo $this->escape(Text::_($teaser['label'])); ?></dt>
									<dd><?php echo $teaser['value']; ?></dd>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>

						<a class="btn btn-outline-primary mt-3" href="<?php echo Route::_('index.php?option=com_joomlalabs_profiles&view=profile&id=' . (string) $item->slug . $profileLinkSuffix); ?>">
							<?php echo Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_READ_MORE'); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php echo $this->pagination->getPagesLinks(); ?>
	<?php endif; ?>
</div>