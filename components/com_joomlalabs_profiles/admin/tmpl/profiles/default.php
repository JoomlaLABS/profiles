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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \JoomlaLabs\Component\Profiles\Administrator\View\Profiles\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$userId    = (int) $user->id;
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder === 'a.ordering';
$saveOrderingUrl = '';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_joomlalabs_profiles&task=profiles.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>
<form action="<?php echo Route::_('index.php?option=com_joomlalabs_profiles'); ?>" method="post" name="adminForm" id="adminForm">
	<div id="j-main-container" class="j-main-container">
		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

		<?php if (empty($this->items)) : ?>
			<div class="alert alert-info">
				<span class="icon-info-circle" aria-hidden="true"></span>
				<span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
				<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
			</div>
		<?php else : ?>
			<table class="table" id="profileList">
				<caption class="visually-hidden">
					<?php echo Text::_('COM_JOOMLALABS_PROFILES_TABLE_CAPTION'); ?>,
					<span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
					<span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
				</caption>
				<thead>
					<tr>
						<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
						<th scope="col" class="w-1 text-center d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
						</th>
						<th scope="col" class="w-1 text-center">
							<?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
						</th>
						<th scope="col">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOOMLALABS_PROFILES_HEADING_DISPLAY_NAME', 'a.display_name', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-15 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-10 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_JOOMLALABS_PROFILES_HEADING_USER', 'linked_user_name', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-10 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
						</th>
						<th scope="col" class="w-5 d-none d-md-table-cell">
							<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
						</th>
					</tr>
				</thead>
				<tbody<?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
				<?php foreach ($this->items as $i => $item) : ?>
					<?php
					$canCreate  = $user->authorise('core.create', 'com_joomlalabs_profiles.category.' . (int) $item->catid);
					$canEdit    = $user->authorise('core.edit', 'com_joomlalabs_profiles.category.' . (int) $item->catid);
					$canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === $userId || $item->checked_out === null;
					$canEditOwn = $user->authorise('core.edit.own', 'com_joomlalabs_profiles.category.' . (int) $item->catid) && (int) $item->created_by === $userId;
					$canChange  = $user->authorise('core.edit.state', 'com_joomlalabs_profiles.category.' . (int) $item->catid) && $canCheckin;
					?>
					<tr class="row<?php echo $i % 2; ?>" data-draggable-group="<?php echo (int) $item->catid; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->display_name); ?></td>
						<td class="text-center d-none d-md-table-cell">
							<?php $iconClass = (!$canChange || !$saveOrder) ? ' inactive' : ''; ?>
							<span class="sortable-handler<?php echo $iconClass; ?>">
								<span class="icon-ellipsis-v" aria-hidden="true"></span>
							</span>
							<?php if ($canChange && $saveOrder) : ?>
								<input type="text" name="order[]" size="5" value="<?php echo (int) $item->ordering; ?>" class="width-20 text-area-order hidden">
							<?php endif; ?>
						</td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'profiles.', $canChange, 'cb'); ?></td>
						<th scope="row" class="has-context">
							<?php if ($item->checked_out) : ?>
								<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'profiles.', $canCheckin); ?>
							<?php endif; ?>
							<?php if ($canEdit || $canEditOwn) : ?>
								<a href="<?php echo Route::_('index.php?option=com_joomlalabs_profiles&task=profile.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->display_name); ?>">
									<?php echo $this->escape($item->display_name); ?>
								</a>
							<?php else : ?>
								<?php echo $this->escape($item->display_name); ?>
							<?php endif; ?>
							<div class="small"><?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?></div>
						</th>
						<td class="d-none d-md-table-cell"><?php echo $this->escape($item->category_title); ?></td>
						<td class="d-none d-md-table-cell"><?php echo $item->user_id ? $this->escape($item->linked_user_name ?: Text::_('COM_JOOMLALABS_PROFILES_USER_LINKED_UNKNOWN')) : Text::_('COM_JOOMLALABS_PROFILES_USER_LINK_NONE'); ?></td>
						<td class="d-none d-md-table-cell"><?php echo $this->escape($item->access_level); ?></td>
						<td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php echo $this->pagination->getListFooter(); ?>
		<?php endif; ?>

		<?php echo $this->filterForm->renderControlFields(); ?>
	</div>
</form>
