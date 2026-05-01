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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \JoomlaLabs\Component\Profiles\Administrator\View\Profiles\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')
    ->useScript('multiselect')
    ->useScript('modal-content-select');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<div class="container-popup">
	<form action="<?php echo Route::_('index.php?option=com_joomlalabs_profiles&view=profiles&layout=modal&tmpl=component&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">
		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

		<?php if (empty($this->items)) : ?>
			<div class="alert alert-info">
				<span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
				<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
			</div>
		<?php else : ?>
			<table class="table table-sm">
				<caption class="visually-hidden">
					<?php echo Text::_('COM_JOOMLALABS_PROFILES_TABLE_CAPTION'); ?>,
					<span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
					<span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
				</caption>
				<thead>
					<tr>
						<th scope="col" class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?></th>
						<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_JOOMLALABS_PROFILES_HEADING_DISPLAY_NAME', 'a.display_name', $listDirn, $listOrder); ?></th>
						<th scope="col" class="w-15 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?></th>
						<th scope="col" class="w-5 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->items as $i => $item) : ?>
						<tr class="row<?php echo $i % 2; ?>">
							<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'profiles.', false, 'cb'); ?></td>
							<th scope="row">
								<?php $attribs = 'data-content-select data-content-type="com_joomlalabs_profiles.profile"'
									. ' data-id="' . (int) $item->id . '"'
									. ' data-title="' . $this->escape($item->display_name) . '"';
								?>
								<a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
									<?php echo $this->escape($item->display_name); ?>
								</a>
								<div class="small"><?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?></div>
							</th>
							<td class="d-none d-md-table-cell"><?php echo $this->escape($item->category_title); ?></td>
							<td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php echo $this->pagination->getListFooter(); ?>
		<?php endif; ?>

		<?php echo $this->filterForm->renderControlFields(); ?>
	</form>
</div>