<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Model;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Versioning\VersionableModelInterface;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use JoomlaLabs\Component\Profiles\Administrator\Table\ProfileTable;

\defined('_JEXEC') or die;

class ProfileModel extends AdminModel implements VersionableModelInterface
{
    use VersionableModelTrait {
        loadHistory as private traitLoadHistory;
    }

    protected $option = 'com_joomlalabs_profiles';

    private ?CMSApplicationInterface $application = null;

    private const FIELDS_CONTEXT = 'com_joomlalabs_profiles.record';

    private const USER_LINK_POLICIES = [
        'optional-many',
        'required-many',
        'optional-single',
        'required-single',
    ];

    public $typeAlias = 'com_joomlalabs_profiles.record';

    protected $formName = 'profile';

    protected $batch_copymove = 'category_id';

    protected $batch_commands = [
        'assetgroup_id' => 'batchAccess',
        'language_id'   => 'batchLanguage',
    ];

    public function setApplication(CMSApplicationInterface $application): void
    {
        $this->application = $application;
    }

    private function getApplication(): CMSApplicationInterface
    {
        return $this->application ?? throw new \RuntimeException('Application not injected into ProfileModel');
    }

    public function loadHistory(int $historyId)
    {
        $itemId = (int) $this->getItemIdFromHistory($historyId);
        $result = $this->traitLoadHistory($historyId);

        if ($result && $itemId > 0) {
            $context = $this->option . '.' . $this->getName();
            $this->ensureProfileActionLogWritten($context, $itemId, false, true);
        }

        return $result;
    }

    protected function populateState()
    {
        parent::populateState();

        // Keep versioning enabled by default when component options were never saved.
        $params = $this->state->get('params');

        if ($params && $params->get('save_history', null) === null) {
            $params->set('save_history', 1);
            $this->setState('params', $params);
        }
    }

    protected function versionHistoryEnabled(string $context): bool
    {
        [$extension] = explode('.', $context, 2);

        // If component options were never saved, keep history enabled by default.
        $saveHistory = ComponentHelper::getParams($extension)->get('save_history', null);

        if ($saveHistory === null) {
            return true;
        }

        return (bool) $saveHistory;
    }

    protected function canDelete($record)
    {
        if (empty($record->id) || (int) $record->published !== -2) {
            return false;
        }

        return $this->getCurrentUser()->authorise('core.delete', 'com_joomlalabs_profiles.category.' . (int) $record->catid);
    }

    protected function canEditState($record)
    {
        if (!empty($record->catid)) {
            return $this->getCurrentUser()->authorise('core.edit.state', 'com_joomlalabs_profiles.category.' . (int) $record->catid);
        }

        return parent::canEditState($record);
    }

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_joomlalabs_profiles.' . $this->formName, $this->formName, ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        if ($formField = $form->getField('catid')) {
            $assignedCatid = $data['catid'] ?? $form->getValue('catid');
            $assignedCatid = \is_array($assignedCatid) ? (int) reset($assignedCatid) : (int) $assignedCatid;

            if ($assignedCatid <= 0) {
                $assignedCatid = (int) $formField->getAttribute('default', 0);

                if ($assignedCatid <= 0) {
                    $catOptions = $formField->options;

                    if ($catOptions && !empty($catOptions[0]->value)) {
                        $assignedCatid = (int) $catOptions[0]->value;
                    }
                }
            }

            $form->setFieldAttribute('catid', 'refresh-enabled', true);
            $form->setFieldAttribute('catid', 'refresh-cat-id', $assignedCatid);
            $form->setFieldAttribute('catid', 'refresh-section', 'profile');

            $displayNamePattern = '';
            $categoryContext    = $assignedCatid > 0 ? $this->loadCategoryPolicy($assignedCatid, false) : null;

            if ($categoryContext) {
                /** @var Registry $policy */
                $policy             = $categoryContext['params'];
                $displayNamePattern = $this->resolveDisplayNamePattern($policy);
            }

            $hasManagedDisplayName = $displayNamePattern !== '';

            $form->setFieldAttribute('display_name', 'readonly', $hasManagedDisplayName ? 'true' : 'false');
            $form->setFieldAttribute('display_name', 'class', $hasManagedDisplayName ? 'readonly' : '');
            $form->setFieldAttribute(
                'display_name',
                'description',
                $hasManagedDisplayName ? 'COM_JOOMLALABS_PROFILES_FIELD_DISPLAY_NAME_DESC' : ''
            );
        }

        if (!$this->canEditState((object) $data)) {
            $form->setFieldAttribute('published', 'disabled', 'true');
            $form->setFieldAttribute('ordering', 'disabled', 'true');

            $form->setFieldAttribute('published', 'filter', 'unset');
            $form->setFieldAttribute('ordering', 'filter', 'unset');
        }

        if ($form->getField('user_id')) {
            $catid = (int) $form->getValue('catid', null, 0);
            $form->setFieldAttribute('user_id', 'required', $this->isUserLinkRequiredForCategory($catid) ? 'true' : 'false');
        }

        return $form;
    }

    /**
     * Return custom fields grouped the same way the legacy template rendered them.
     *
     * @param mixed $item Current record object/array.
     *
     * @return array<int, array{label:string,description:string,fields:array}>
     */
    public function getCustomFieldCards($item): array
    {
        $fieldsContext = self::FIELDS_CONTEXT;
        $fields        = FieldsHelper::getFields($fieldsContext, $item, true, null, true);

        if (empty($fields)) {
            return [];
        }

        $defaultGroup = (object) [
            'id'          => 0,
            'title'       => '',
            'description' => '',
        ];

        $user       = $this->getApplication()->getIdentity();
        $viewlevels = $user ? array_values(array_unique(array_map('intval', $user->getAuthorisedViewLevels()))) : [0];
        $db         = $this->getDatabase();
        $query      = $db->createQuery()
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('description'),
            ])
            ->from($db->quoteName('#__fields_groups'))
            ->where($db->quoteName('context') . ' = :context')
            ->whereIn($db->quoteName('state'), [0, 1], ParameterType::INTEGER)
            ->whereIn($db->quoteName('access'), $viewlevels, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC')
            ->bind(':context', $fieldsContext);

        $db->setQuery($query);
        $groups        = array_merge([$defaultGroup], $db->loadObjectList() ?: []);
        $fieldsByGroup = [0 => []];

        foreach ($fields as $field) {
            $groupId = (int) ($field->group_id ?? 0);

            if (!isset($fieldsByGroup[$groupId])) {
                $fieldsByGroup[$groupId] = [];
            }

            $fieldsByGroup[$groupId][] = $field;
        }

        $cards = [];

        foreach ($groups as $group) {
            $groupId = (int) ($group->id ?? 0);

            if (empty($fieldsByGroup[$groupId])) {
                continue;
            }

            $label = trim((string) ($group->title ?? ''));

            if ($label === '') {
                $labelKey = strtoupper('com_joomlalabs_profiles' . '_fields_' . 'profile' . '_label');

                if (!$this->getApplication()->getLanguage()->hasKey($labelKey)) {
                    $labelKey = 'JGLOBAL_FIELDS';
                }

                $label = $labelKey;
            }

            $description = trim(strip_tags((string) ($group->description ?? '')));

            if ($description === '') {
                $descriptionKey = strtoupper('com_joomlalabs_profiles' . '_fields_' . 'profile' . '_desc');

                if ($this->getApplication()->getLanguage()->hasKey($descriptionKey)) {
                    $description = $descriptionKey;
                }
            }

            $cards[] = [
                'label'       => $label,
                'description' => $description,
                'fields'      => $fieldsByGroup[$groupId],
            ];
        }

        return $cards;
    }

    /**
     * Render custom fields grouped into cards.
     *
     * @param mixed $item Current record object/array.
     * @param mixed $form
     *
     * @return string
     */
    public function renderCustomFieldCardsHtml($form, $item = null): string
    {
        if (!($form instanceof Form)) {
            return '';
        }

        // Get fieldsets within the 'com_fields' group (matching backup version approach)
        $fieldCards = [];

        foreach ($form->getFieldsets('com_fields') as $fieldsetName => $fieldset) {
            $fields = $form->getFieldset($fieldsetName, 'com_fields');

            if (!$fields) {
                continue;
            }

            $fieldCards[] = [
                'label'       => (string) ($fieldset->label ?: 'JGLOBAL_FIELDS'),
                'description' => (string) ($fieldset->description ?? ''),
                'fields'      => $fields,
            ];
        }

        // Fallback for legacy field structure: if no fieldsets in com_fields, try getting all fields in com_fields group
        $legacyFields = [];

        if (empty($fieldCards)) {
            $legacyFields = $form->getGroup('com_fields');
        }

        // Generate HTML
        $html = [];

        foreach ($fieldCards as $fieldCard) {
            $html[] = '<div class="card mb-3">';
            $html[] = '<div class="card-header">' . Text::_($fieldCard['label']) . '</div>';
            $html[] = '<div class="card-body">';

            if (trim($fieldCard['description']) !== '') {
                $html[] = '<p class="text-muted small mb-3">' . Text::_($fieldCard['description']) . '</p>';
            }

            foreach ($fieldCard['fields'] as $field) {
                $html[] = $field->renderField();
            }

            $html[] = '</div>';
            $html[] = '</div>';
        }

        // Fallback rendering if no structured fieldsets were found
        if (empty($fieldCards) && $legacyFields) {
            $html[] = '<div class="card mb-3">';
            $html[] = '<div class="card-header">' . Text::_('JGLOBAL_FIELDS') . '</div>';
            $html[] = '<div class="card-body">';

            foreach ($legacyFields as $field) {
                $html[] = $field->renderField();
            }

            $html[] = '</div>';
            $html[] = '</div>';
        }

        return implode("\n", $html);
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item) {
            $item->params   = (new Registry($item->params))->toArray();
            $item->metadata = (new Registry($item->metadata))->toArray();
        }

        return $item;
    }

    protected function loadFormData()
    {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app      = $this->getApplication();
        $recordId = (int) $this->getState('profile.id');

        if ($recordId <= 0) {
            $recordId = $app->getInput()->getInt('id', (int) $app->getUserState('com_joomlalabs_profiles.edit.profile.id', 0));

            if ($recordId > 0) {
                $this->setState('profile.id', $recordId);
            }
        }

        $data = $app->getUserState('com_joomlalabs_profiles.edit.profile.data', []);

        if (!empty($data)) {
            $stateDataId = 0;

            if (\is_array($data)) {
                $stateDataId = (int) ($data['id'] ?? 0);
            } elseif (\is_object($data)) {
                $stateDataId = (int) ($data->id ?? 0);
            }

            // If session draft belongs to another record, drop it to avoid blank/wrong edit forms.
            if ($recordId > 0 && $stateDataId !== $recordId) {
                $data = [];
                $app->setUserState('com_joomlalabs_profiles.edit.profile.data', []);
            }

            // For edit pages, merge draft data with DB row so missing keys don't blank the form.
            if ($recordId > 0 && !empty($data)) {
                $item = $this->getItem($recordId);

                if ($item) {
                    if (\is_array($data)) {
                        $data = array_replace((array) $item, $data);

                        if ((int) ($data['id'] ?? 0) <= 0) {
                            $data['id'] = (int) ($item->id ?? 0);
                        }

                        if ((int) ($data['catid'] ?? 0) <= 0) {
                            $data['catid'] = (int) ($item->catid ?? 0);
                        }
                    } elseif (\is_object($data)) {
                        foreach (get_object_vars($item) as $key => $value) {
                            if (!property_exists($data, $key)) {
                                $data->$key = $value;
                            }
                        }

                        if ((int) ($data->id ?? 0) <= 0) {
                            $data->id = (int) ($item->id ?? 0);
                        }

                        if ((int) ($data->catid ?? 0) <= 0) {
                            $data->catid = (int) ($item->catid ?? 0);
                        }
                    }
                }
            }
        }

        if (empty($data)) {
            $data = $this->getItem($recordId > 0 ? $recordId : null);

            if ($recordId === 0) {
                $defaultCatid = $app->getInput()->getInt('catid', 0);

                if ($defaultCatid <= 0) {
                    $defaultCategoryState = $app->getUserState('com_joomlalabs_profiles.profiles.filter.category_id', []);

                    if (\is_array($defaultCategoryState)) {
                        $defaultCategoryState = array_values(array_filter(array_map('intval', $defaultCategoryState), static fn (int $categoryId): bool => $categoryId > 0));
                        $defaultCatid         = (int) ($defaultCategoryState[0] ?? 0);
                    } else {
                        $defaultCatid = is_numeric($defaultCategoryState) ? (int) $defaultCategoryState : 0;
                    }
                }

                if ($defaultCatid <= 0) {
                    $defaultCatid = $this->getFirstAssignableCategoryId();
                }

                if (\is_array($data)) {
                    $data['catid'] = $defaultCatid;
                } else {
                    $data->catid = $defaultCatid;
                }
            }
        }

        if ($recordId === 0) {
            $catid = \is_array($data) ? (int) ($data['catid'] ?? 0) : (int) ($data->catid ?? 0);

            if ($catid <= 0) {
                $fallbackCatid = $this->getFirstAssignableCategoryId();

                if ($fallbackCatid > 0) {
                    if (\is_array($data)) {
                        $data['catid'] = $fallbackCatid;
                    } else {
                        $data->catid = $fallbackCatid;
                    }
                }
            }
        }

        $this->preprocessData('com_joomlalabs_profiles.record', $data);

        return $data;
    }

    private function getFirstAssignableCategoryId(): int
    {
        $db        = $this->getDatabase();
        $user      = $this->getCurrentUser();
        $extension = 'com_joomlalabs_profiles';
        $states    = [0, 1];

        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->whereIn($db->quoteName('published'), $states, ParameterType::INTEGER)
            ->order($db->quoteName('lft') . ' ASC')
            ->bind(':extension', $extension);

        if (!$user->authorise('core.admin')) {
            $groups = array_values(array_unique(array_map('intval', $user->getAuthorisedViewLevels())));

            if ($groups === []) {
                return 0;
            }

            $query->whereIn($db->quoteName('access'), $groups, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $categoryIds = array_values(array_filter(array_map('intval', (array) $db->loadColumn()), static fn (int $categoryId): bool => $categoryId > 0));

        foreach ($categoryIds as $categoryId) {
            if ($user->authorise('core.create', $extension . '.category.' . $categoryId)) {
                return $categoryId;
            }
        }

        return 0;
    }

    public function save($data)
    {
        $app       = $this->getApplication();
        $task      = strtolower((string) $app->getInput()->getCmd('task', ''));
        $isCopy    = $task === 'save2copy';
        $isNew     = ((int) ($data['id'] ?? 0) <= 0) || $isCopy;
        $isRestore = !$isNew && str_ends_with($task, '.loadhistory');
        $context   = $this->option . '.' . $this->getName();

        // Register all actionlog listeners on this model dispatcher as well.
        PluginHelper::importPlugin('actionlog', null, true, $this->getDispatcher());

        $customFieldsForSave = $this->resolveSubmittedCustomFieldsForPersistence($data);

        if ($customFieldsForSave !== []) {
            $data['com_fields'] = $customFieldsForSave;
        }

        if (!$this->applyBusinessRules($data)) {
            return false;
        }

        if ($isCopy) {
            $data['id']        = 0;
            $data['published'] = 0;
            $data['alias']     = '';
        }

        if (!parent::save($data)) {

            return false;
        }

        $itemId = (int) $this->getState($this->getName() . '.id');
        $catid  = (int) ($data['catid'] ?? 0);

        if ($itemId > 0 && $catid > 0 && $customFieldsForSave !== []) {
            // Guarantee persistence in #__fields_values even when the system fields plugin save hook is skipped.
            $this->persistSubmittedCustomFieldValues($itemId, $catid, $customFieldsForSave);

        }

        // Fallback guard: if no actionlog row was written by subscribers, write one directly.
        $this->ensureProfileActionLogWritten($context, $itemId, $isNew, $isRestore);

        return true;
    }

    private function ensureProfileActionLogWritten(string $context, int $itemId, bool $isNew, bool $isRestore): void
    {
        if ($itemId <= 0) {
            return;
        }

        $messageLanguageKey = $isNew
            ? 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_ADDED'
            : ($isRestore
                ? 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_RESTORED'
                : 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_UPDATED');

        if ($this->hasRecentActionLogEntry($context, $itemId, $messageLanguageKey)) {
            return;
        }

        $item = $this->getItem($itemId);

        $message = [
            'action'      => $isRestore ? 'restore' : ($isNew ? 'add' : 'update'),
            'type'        => 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_TYPE_PROFILE',
            'id'          => $itemId,
            'title'       => (string) ($item->display_name ?? ''),
            'itemlink'    => 'index.php?option=com_joomlalabs_profiles&task=profile.edit&id=' . $itemId,
            'userid'      => 0,
            'username'    => 'System',
            'accountlink' => 'index.php?option=com_users&view=users',
        ];

        $user = $this->getApplication()->getIdentity();

        if ($user && (int) ($user->id ?? 0) > 0) {
            $message['userid']      = (int) $user->id;
            $message['username']    = (string) $user->username;
            $message['accountlink'] = 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id;
        }

        if ($isRestore) {
            $versionId = (int) $this->getApplication()->getInput()->getInt('version_id', 0);

            if ($versionId > 0) {
                $message['version_id'] = $versionId;
            }
        }

        $this->writeActionLogEntry($messageLanguageKey, $context, $message);
    }

    private function hasRecentActionLogEntry(string $context, int $itemId, string $messageLanguageKey): bool
    {
        $db         = $this->getDatabase();
        // Keep deduplication window tight to avoid dropping legitimate rapid edits.
        $recentFrom = (new Date('-2 seconds'))->toSql();
        $messageKey = strtoupper($messageLanguageKey);
        $query      = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :context')
            ->where($db->quoteName('item_id') . ' = :item_id')
            ->where($db->quoteName('message_language_key') . ' = :message_key')
            ->where($db->quoteName('log_date') . ' >= :recent_from')
            ->bind(':context', $context)
            ->bind(':item_id', $itemId, ParameterType::INTEGER)
            ->bind(':message_key', $messageKey)
            ->bind(':recent_from', $recentFrom);

        $db->setQuery($query);

        try {
            return (int) $db->loadResult() > 0;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function writeActionLogEntry(string $messageLanguageKey, string $context, array $message): void
    {
        $component = $this->getApplication()->bootComponent('com_actionlogs');

        if (!$component instanceof MVCFactoryServiceInterface) {
            return;
        }

        $model = $component->getMVCFactory()->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof ActionlogModel) {
            return;
        }

        $model->addLog([$message], $messageLanguageKey, $context);
    }

    private function resolveSubmittedCustomFieldsForPersistence(array $data): array
    {
        $submittedContext = $this->resolveSubmittedCustomFieldsContext($data);
        $submitted        = $submittedContext['submitted'];

        if ($submitted === []) {

            return [];
        }

        $valuesByName = [];
        $valuesById   = [];

        foreach ($submitted as $key => $value) {
            if (\is_int($key) || ctype_digit((string) $key)) {
                $valuesById[(int) $key] = $value;

                continue;
            }

            $fieldName = trim((string) $key);

            if ($fieldName !== '') {
                $valuesByName[$fieldName] = $value;
            }
        }

        if ($valuesById !== []) {
            $nameMap = $this->mapFieldIdsToNames(array_keys($valuesById));

            foreach ($valuesById as $fieldId => $value) {
                $fieldName = trim((string) ($nameMap[$fieldId] ?? ''));

                if ($fieldName !== '') {
                    $valuesByName[$fieldName] = $value;
                }

            }
        }

        if ($valuesByName === []) {

            return [];
        }

        $resolved = $this->mapSubmittedFieldNamesToStoredNames($valuesByName);

        return $resolved;
    }

    protected function prepareTable($table)
    {
        $date = (new Date())->toSql();

        if (empty($table->id)) {
            $table->created = $date;
            $table->version = 1;
        } else {
            $table->modified    = $date;
            $table->modified_by = $this->getCurrentUser()->id;
            $table->version++;
        }

        if ($table instanceof ProfileTable) {
            $table->generateAlias();
        }
    }

    protected function getReorderConditions($table)
    {
        return [
            $this->getDatabase()->quoteName('catid') . ' = ' . (int) $table->catid,
        ];
    }

    private function applyBusinessRules(array &$data): bool
    {
        $catid = (int) ($data['catid'] ?? 0);

        if ($catid <= 0) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));

            return false;
        }

        $categoryContext = $this->loadCategoryPolicy($catid);

        if (!$categoryContext) {
            return false;
        }

        /** @var Registry $policy */
        $policy               = $categoryContext['params'];
        $recordId             = (int) ($data['id'] ?? 0);
        $displayPattern       = $this->resolveDisplayNamePattern($policy);
        $submittedDisplayName = preg_replace('/\s+/', ' ', trim((string) ($data['display_name'] ?? '')))
            ?? trim((string) ($data['display_name'] ?? ''));
        $displayNameOverride = preg_replace('/\s+/', ' ', trim((string) ($data['_display_name_override'] ?? '')))
            ?? trim((string) ($data['_display_name_override'] ?? ''));
        unset($data['_display_name_override']);

        if ($displayNameOverride !== '') {
            $displayName = $displayNameOverride;
        } elseif ($displayPattern === '' && $submittedDisplayName !== '') {
            $displayName = $submittedDisplayName;
        } else {
            $customFields    = $this->resolveSubmittedCustomFields($data);
            $existingFields  = $this->loadExistingCustomFieldValues($recordId, $catid);
            $existingProfile = $this->loadExistingProfileValues($recordId);
            $fieldValues     = array_replace($existingFields, $customFields);
            $displayName     = $this->resolveDisplayName(
                $displayPattern,
                $fieldValues,
                (string) ($existingProfile['display_name'] ?? ''),
                $recordId > 0
            );
        }

        if ($displayName === '') {
            $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_DISPLAY_NAME_COMPOSITION_FAILED'));

            return false;
        }

        $data['profile_type'] = $this->resolveProfileTypeKey($catid, (string) ($categoryContext['alias'] ?? ''), $policy);
        $data['display_name'] = $displayName;

        $userLinkPolicy = $this->normalizeUserLinkPolicy((string) $policy->get('user_link_policy', 'optional-many'));
        $userId         = (int) ($data['user_id'] ?? 0);

        if ($this->isUserLinkPolicyRequired($userLinkPolicy) && $userId <= 0) {
            $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_USER_LINK_REQUIRED'));

            return false;
        }

        if (str_ends_with($userLinkPolicy, 'single') && $userId > 0 && !$this->ensureSingleUserConstraint($data, $userId)) {
            return false;
        }

        $data['user_id'] = $userId > 0 ? $userId : null;

        return true;
    }

    private function normalizeUserLinkPolicy(string $policy): string
    {
        $policy = trim($policy);

        if (!\in_array($policy, self::USER_LINK_POLICIES, true)) {
            return 'optional-many';
        }

        return $policy;
    }

    private function isUserLinkPolicyRequired(string $policy): bool
    {
        return str_starts_with($policy, 'required');
    }

    private function isUserLinkRequiredForCategory(int $catid): bool
    {
        if ($catid <= 0) {
            return false;
        }

        $categoryContext = $this->loadCategoryPolicy($catid, false);

        if (!$categoryContext) {
            return false;
        }

        /** @var Registry $policy */
        $policy = $categoryContext['params'];

        return $this->isUserLinkPolicyRequired(
            $this->normalizeUserLinkPolicy((string) $policy->get('user_link_policy', 'optional-many'))
        );
    }

    private function resolveDisplayNamePattern(Registry $policy): string
    {
        return trim((string) $policy->get('display_name_pattern', ''));
    }

    private function resolveDisplayName(string $pattern, array $values, string $existingDisplayName, bool $isEdit): string
    {
        if ($pattern !== '') {
            return $this->composeDisplayName($pattern, $values);
        }

        if ($isEdit && trim($existingDisplayName) !== '') {
            return preg_replace('/\s+/', ' ', trim($existingDisplayName)) ?? trim($existingDisplayName);
        }

        return $this->composeDisplayNameFromValues($values);
    }

    private function composeDisplayNameFromValues(array $values): string
    {
        $parts = [];

        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value === '' || \in_array($value, $parts, true)) {
                continue;
            }

            $parts[] = $value;

            if (\count($parts) >= 3) {
                break;
            }
        }

        $displayName = implode(' ', $parts);

        return preg_replace('/\s+/', ' ', trim($displayName)) ?? trim($displayName);
    }

    private function resolveProfileTypeKey(int $catid, string $categoryAlias, Registry $policy): string
    {
        foreach (['profile_type_key', 'profile_type', 'allowed_profile_type'] as $paramName) {
            $configuredKey = $this->normalizeProfileTypeKey((string) $policy->get($paramName, ''));

            if ($configuredKey !== '') {
                return substr($configuredKey, 0, 20);
            }
        }

        $aliasKey = $this->normalizeProfileTypeKey($categoryAlias);

        if ($aliasKey !== '' && \strlen($aliasKey) <= 20) {
            return $aliasKey;
        }

        return 'category-' . $catid;
    }

    private function normalizeProfileTypeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace('_', '-', $key);
        $key = preg_replace('/[^a-z0-9-]+/', '-', $key) ?? $key;
        $key = preg_replace('/-+/', '-', $key) ?? $key;

        return trim($key, '-');
    }

    private function resolveSubmittedCustomFields(array $data): array
    {
        $submittedContext = $this->resolveSubmittedCustomFieldsContext($data);
        $submitted        = $submittedContext['submitted'];

        if ($submitted === []) {
            return [];
        }

        $valuesByName = [];
        $valuesById   = [];

        foreach ($submitted as $key => $value) {
            $normalized = $this->normalizeCustomFieldValue($value);

            if (\is_int($key) || ctype_digit((string) $key)) {
                $valuesById[(int) $key] = $normalized;

                continue;
            }

            $normalizedKey = $this->normalizeFieldNameKey((string) $key);

            if ($normalizedKey !== '') {
                $valuesByName[$normalizedKey] = $normalized;
            }
        }

        if ($valuesById !== []) {
            $nameMap = $this->mapFieldIdsToNames(array_keys($valuesById));

            foreach ($valuesById as $fieldId => $value) {
                $fieldName = $this->normalizeFieldNameKey((string) ($nameMap[$fieldId] ?? ''));

                if ($fieldName !== '') {
                    $valuesByName[$fieldName] = $value;
                }
            }
        }

        return $valuesByName;
    }

    private function resolveSubmittedCustomFieldsContext(array $data): array
    {
        $jform          = $this->getApplication()->getInput()->get('jform', [], 'array');
        $jformComFields = [];
        $dataComFields  = [];

        if (isset($jform['com_fields']) && \is_array($jform['com_fields'])) {
            $jformComFields = $jform['com_fields'];
        }

        if (isset($data['com_fields']) && \is_array($data['com_fields'])) {
            $dataComFields = $data['com_fields'];
        }

        // If explicit com_fields are passed to model->save(), trust them and ignore ambient jform payload.
        if ($dataComFields !== []) {
            return [
                'source'           => 'data_com_fields_only',
                'jform_com_fields' => $jformComFields,
                'data_com_fields'  => $dataComFields,
                'submitted'        => $dataComFields,
            ];
        }

        return [
            'source'           => 'jform_com_fields',
            'jform_com_fields' => $jformComFields,
            'data_com_fields'  => $dataComFields,
            'submitted'        => $jformComFields,
        ];
    }

    private function mapFieldIdsToNames(array $fieldIds): array
    {
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn (int $id): bool => $id > 0)));

        if ($fieldIds === []) {
            return [];
        }

        $db            = $this->getDatabase();
        $fieldsContext = self::FIELDS_CONTEXT;
        $query         = $db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('name')])
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':context', $fieldsContext)
            ->whereIn($db->quoteName('id'), $fieldIds);

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];
        $map  = [];

        foreach ($rows as $row) {
            $map[(int) $row->id] = (string) $row->name;
        }

        return $map;
    }

    private function mapSubmittedFieldNamesToStoredNames(array $valuesByName): array
    {
        $db            = $this->getDatabase();
        $fieldsContext = self::FIELDS_CONTEXT;
        $query         = $db->createQuery()
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':context', $fieldsContext);

        $db->setQuery($query);

        $normalizedToStored = [];

        foreach (($db->loadColumn() ?: []) as $storedName) {
            $storedName = trim((string) $storedName);

            if ($storedName === '') {
                continue;
            }

            $normalized = $this->normalizeFieldNameKey($storedName);

            if ($normalized !== '' && !isset($normalizedToStored[$normalized])) {
                $normalizedToStored[$normalized] = $storedName;
            }
        }

        if ($normalizedToStored === []) {

            return $valuesByName;
        }

        $resolved = [];

        foreach ($valuesByName as $submittedName => $value) {
            $submittedName = (string) $submittedName;
            $normalized    = $this->normalizeFieldNameKey($submittedName);
            $storedName    = $normalized !== '' ? ($normalizedToStored[$normalized] ?? $submittedName) : $submittedName;

            $resolved[$storedName] = $value;

            if ($storedName !== $submittedName) {
            }
        }

        return $resolved;
    }

    private function persistSubmittedCustomFieldValues(int $itemId, int $catid, array $submittedValues): void
    {
        $db            = $this->getDatabase();
        $fieldsContext = self::FIELDS_CONTEXT;
        $query         = $db->createQuery()
            ->select([$db->quoteName('f.id'), $db->quoteName('f.name')])
            ->from($db->quoteName('#__fields', 'f'))
            ->join('INNER', $db->quoteName('#__fields_categories', 'fc') . ' ON ' . $db->quoteName('fc.field_id') . ' = ' . $db->quoteName('f.id'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->bind(':context', $fieldsContext)
            ->where($db->quoteName('f.state') . ' = 1')
            ->where($db->quoteName('fc.category_id') . ' = :catid')
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);

        $fields        = $db->loadObjectList() ?: [];
        $allowedByName = [];

        foreach ($fields as $field) {
            $fieldName = trim((string) ($field->name ?? ''));

            if ($fieldName !== '') {
                $allowedByName[$fieldName] = true;
            }
        }

        foreach ($submittedValues as $submittedName => $submittedValue) {
            $submittedName = trim((string) $submittedName);

            if ($submittedName === '' || isset($allowedByName[$submittedName])) {
                continue;
            }

        }

        foreach ($fields as $field) {
            $fieldId   = (int) ($field->id ?? 0);
            $fieldName = trim((string) ($field->name ?? ''));

            if ($fieldId <= 0 || $fieldName === '' || !\array_key_exists($fieldName, $submittedValues)) {
                if ($fieldId > 0 && $fieldName !== '') {
                }

                continue;
            }

            $this->replaceCustomFieldRows($fieldId, $itemId, $submittedValues[$fieldName]);
        }
    }

    private function replaceCustomFieldRows(int $fieldId, int $itemId, $value): void
    {
        $db         = $this->getDatabase();
        $itemIdText = (string) $itemId;
        $query      = $db->createQuery()
            ->delete($db->quoteName('#__fields_values'))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->where($db->quoteName('item_id') . ' = :item_id')
            ->bind(':field_id', $fieldId, ParameterType::INTEGER)
            ->bind(':item_id', $itemIdText);

        $db->setQuery($query)->execute();

        $rows = $this->normalizeCustomFieldRowsForStorage($value);

        foreach ($rows as $rowValue) {
            $insert           = new \stdClass();
            $insert->field_id = $fieldId;
            $insert->item_id  = $itemIdText;
            $insert->value    = $rowValue;

            $db->insertObject('#__fields_values', $insert);
        }
    }

    private function normalizeCustomFieldRowsForStorage($value): array
    {
        if ($value === false || $value === null) {
            return [];
        }

        if (!\is_array($value)) {
            $scalar = (string) $value;

            return \strlen($scalar) > 0 ? [$scalar] : [];
        }

        if ($value === []) {
            return [];
        }

        $isNestedOrAssociative = \count($value, COUNT_NORMAL) !== \count($value, COUNT_RECURSIVE)
            || !\count(array_filter(array_keys($value), 'is_numeric'));

        if ($isNestedOrAssociative) {
            $encoded = json_encode($value);

            return ($encoded !== false && $encoded !== '') ? [$encoded] : [];
        }

        $rows = [];

        foreach ($value as $item) {
            if (\is_array($item) || \is_object($item)) {
                $encoded = json_encode($item);

                if ($encoded !== false && $encoded !== '') {
                    $rows[] = $encoded;
                }

                continue;
            }

            $scalar = (string) $item;

            if (\strlen($scalar) > 0) {
                $rows[] = $scalar;
            }
        }

        return $rows;
    }

    private function normalizeCustomFieldValue($value): string
    {
        if (!\is_array($value)) {
            return trim((string) $value);
        }

        $parts = [];

        array_walk_recursive(
            $value,
            static function ($item) use (&$parts): void {
                $item = trim((string) $item);

                if ($item !== '') {
                    $parts[] = $item;
                }
            }
        );

        return implode(' ', $parts);
    }

    private function normalizeFieldNameKey(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return '';
        }

        return str_replace('-', '_', $name);
    }

    private function composeDisplayName(string $pattern, array $values): string
    {
        $displayName = preg_replace_callback(
            '/\{([a-z0-9_-]+)\}/i',
            function (array $matches) use ($values): string {
                $fieldName = $this->normalizeFieldNameKey((string) ($matches[1] ?? ''));

                if ($fieldName === '') {
                    return '';
                }

                return (string) ($values[$fieldName] ?? '');
            },
            $pattern
        );

        $displayName = preg_replace('/\s+/', ' ', trim((string) $displayName)) ?? trim((string) $displayName);

        return $displayName;
    }

    private function loadExistingCustomFieldValues(int $recordId, int $catid): array
    {
        if ($recordId <= 0 || $catid <= 0) {
            return [];
        }

        $db            = $this->getDatabase();
        $fieldsContext = self::FIELDS_CONTEXT;
        $itemId        = (string) $recordId;

        $query = $db->createQuery()
            ->select([$db->quoteName('f.name'), $db->quoteName('fv.value')])
            ->from($db->quoteName('#__fields_values', 'fv'))
            ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('fv.field_id'))
            ->join('INNER', $db->quoteName('#__fields_categories', 'fc') . ' ON ' . $db->quoteName('fc.field_id') . ' = ' . $db->quoteName('f.id'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->bind(':context', $fieldsContext)
            ->where($db->quoteName('fv.item_id') . ' = :item_id')
            ->bind(':item_id', $itemId)
            ->where($db->quoteName('fc.category_id') . ' = :catid')
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);

        $rows   = $db->loadObjectList() ?: [];
        $values = [];

        foreach ($rows as $row) {
            $fieldName = $this->normalizeFieldNameKey((string) ($row->name ?? ''));

            if ($fieldName === '') {
                continue;
            }

            $values[$fieldName] = $this->normalizeCustomFieldValue((string) ($row->value ?? ''));
        }

        return $values;
    }

    private function loadExistingProfileValues(int $recordId): array
    {
        if ($recordId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select(
                [
                    $db->quoteName('display_name'),
                ]
            )
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $recordId, ParameterType::INTEGER);

        $db->setQuery($query);
        $row = $db->loadAssoc();

        if (!\is_array($row)) {
            return [];
        }

        return [
            'display_name' => (string) ($row['display_name'] ?? ''),
        ];
    }

    private function loadCategoryPolicy(int $catid, bool $setError = true): ?array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([$db->quoteName('extension'), $db->quoteName('params'), $db->quoteName('alias')])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :catid')
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);
        $category = $db->loadObject();

        if (!$category || (string) $category->extension !== 'com_joomlalabs_profiles') {
            if ($setError) {
                $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_INVALID_CATEGORY'));
            }

            return null;
        }

        return [
            'params' => new Registry((string) $category->params),
            'alias'  => (string) ($category->alias ?? ''),
        ];
    }

    private function ensureSingleUserConstraint(array $data, int $userId): bool
    {
        $db        = $this->getDatabase();
        $currentId = (int) ($data['id'] ?? 0);
        $catid     = (int) ($data['catid'] ?? 0);

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('catid') . ' = :catid')
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->bind(':catid', $catid, ParameterType::INTEGER)
            ->bind(':user_id', $userId, ParameterType::INTEGER);

        if ($currentId > 0) {
            $query->where($db->quoteName('id') . ' <> :id')
                ->bind(':id', $currentId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_USER_ALREADY_HAS_PROFILE_IN_CATEGORY'));

            return false;
        }

        return true;
    }
}
