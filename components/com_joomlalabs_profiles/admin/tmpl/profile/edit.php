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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$profileModel = $this->getModel();

?>

<form action="<?php echo Route::_('index.php?option=com_joomlalabs_profiles&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="profile-form" class="form-validate">
	<div class="row title-alias form-vertical mb-3">
		<div class="col-12 col-md-6">
			<?php echo $this->form->renderField('display_name'); ?>
		</div>
		<div class="col-12 col-md-6">
			<?php echo $this->form->renderField('alias'); ?>
		</div>
	</div>

	<div class="main">
		<div class="row">
			<div class="col-lg-9">
				<div id="custom-fields-container">
					<?php if ($profileModel instanceof \JoomlaLabs\Component\Profiles\Administrator\Model\ProfileModel) : ?>
						<?php echo $profileModel->renderCustomFieldCardsHtml($this->form, $this->item); ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="col-lg-3">
				<fieldset class="form-vertical">
					<legend class="visually-hidden"><?php echo Text::_('JSTATUS'); ?></legend>
					<?php echo $this->form->renderField('published'); ?>
					<?php echo $this->form->renderField('catid'); ?>
					<?php echo $this->form->renderField('access'); ?>
					<?php echo $this->form->renderField('language'); ?>
					<?php echo $this->form->renderField('version_note'); ?>
					<?php echo $this->form->renderField('user_id'); ?>
				</fieldset>
			</div>
		</div>
	</div>

	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
