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
use Joomla\CMS\Table\Table;
use Joomla\Component\Fields\Administrator\Table\FieldTable;
use Joomla\Component\Fields\Administrator\Table\GroupTable;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Installer script for com_joomlalabs_profiles.
 */
class Com_Joomlalabs_profilesInstallerScript
{
    private const FIELDS_CONTEXT = 'com_joomlalabs_profiles.record';

    private const USER_LINK_POLICIES = [
        'optional-many',
        'required-many',
        'optional-single',
        'required-single',
    ];

    private const GROUP_IDENTITY_TITLE = 'Identity';

    private const GROUP_ADDRESS_TITLE = 'Address';

    private const GROUP_CONTACT_TITLE = 'Contact';

    private const COMPONENT_OPTION = 'com_joomlalabs_profiles';

    private const DEFAULT_CATEGORY_PARENT_ID = 1;

    /**
     * Run postflight tasks after install/update/discover_install.
     *
     * @param string $type Install type.
     * @param object $parent Installer parent adapter.
     *
     * @return void
     */
    public function postflight($type, $parent): void
    {
        if (!\in_array((string) $type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        try {
            $this->ensureAdminMenuLinksCanonical();
            $this->ensureProfilesTableExists();
            $this->pruneLegacyCoreNameColumns();

            if ($this->shouldBootstrapCoreFields()) {
                $sampleCategoryIds = $this->ensureDefaultCategories();
                $this->bootstrapCoreFields($sampleCategoryIds);
            }
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                'com_joomlalabs_profiles: unable to bootstrap core fields. ' . $e->getMessage(),
                'warning'
            );
        }
    }

    private function shouldBootstrapCoreFields(): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return !$this->hasExistingBootstrapData($db);
    }

    private function hasExistingBootstrapData(DatabaseInterface $db): bool
    {
        if ($this->hasExistingCategories($db)) {
            return true;
        }

        if ($this->hasExistingFieldGroups($db)) {
            return true;
        }

        return $this->hasExistingFields($db);
    }

    private function hasExistingCategories(DatabaseInterface $db): bool
    {
        $extension = self::COMPONENT_OPTION;

        $query = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->bind(':extension', $extension);

        $db->setQuery($query, 0, 1);

        return $db->loadResult() !== null;
    }

    private function hasExistingFieldGroups(DatabaseInterface $db): bool
    {
        $context = self::FIELDS_CONTEXT;

        $query = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__fields_groups'))
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':context', $context);

        $db->setQuery($query, 0, 1);

        return $db->loadResult() !== null;
    }

    private function hasExistingFields(DatabaseInterface $db): bool
    {
        $context = self::FIELDS_CONTEXT;

        $query = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':context', $context);

        $db->setQuery($query, 0, 1);

        return $db->loadResult() !== null;
    }

    /**
     * Create default field group and baseline fields when missing.
     *
     * @return void
     */
    private function bootstrapCoreFields(array $categoryIds): void
    {
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $groupIds = $this->getOrCreateFieldGroups($db);

        $this->normalizeFieldDefaultValues($db);

        $definitions = [
            ['name' => 'first-name', 'title' => 'First Name', 'type' => 'text', 'scope_type' => 'person', 'group' => 'identity', 'categories' => ['person'], 'required' => 1],
            ['name' => 'last-name', 'title' => 'Last Name', 'type' => 'text', 'scope_type' => 'person', 'group' => 'identity', 'categories' => ['person'], 'required' => 1],
            ['name' => 'company-name', 'title' => 'Company Name', 'type' => 'text', 'scope_type' => 'legal', 'group' => 'identity', 'categories' => ['legal'], 'required' => 1],
            ['name' => 'date-of-birth', 'title' => 'Date of Birth', 'type' => 'calendar', 'scope_type' => 'person', 'group' => 'identity', 'categories' => ['person']],
            ['name' => 'tax-code', 'title' => 'Tax Code', 'type' => 'text', 'scope_type' => 'person', 'group' => 'identity', 'categories' => ['person']],
            ['name' => 'vat-number', 'title' => 'VAT Number', 'type' => 'text', 'scope_type' => 'legal', 'group' => 'identity', 'categories' => ['legal']],
            ['name' => 'address', 'title' => 'Address', 'type' => 'textarea', 'scope_type' => 'both', 'group' => 'address', 'categories' => ['person', 'legal']],
            ['name' => 'city-or-suburb', 'title' => 'City / Suburb', 'type' => 'text', 'scope_type' => 'both', 'group' => 'address', 'categories' => ['person', 'legal']],
            ['name' => 'state-or-country', 'title' => 'State / Country', 'type' => 'text', 'scope_type' => 'both', 'group' => 'address', 'categories' => ['person', 'legal']],
            ['name' => 'postal-zip-code', 'title' => 'Postal / ZIP Code', 'type' => 'text', 'scope_type' => 'both', 'group' => 'address', 'categories' => ['person', 'legal']],
            ['name' => 'email', 'title' => 'Email', 'type' => 'text', 'scope_type' => 'both', 'group' => 'contact', 'categories' => ['person', 'legal']],
            ['name' => 'phone', 'title' => 'Phone', 'type' => 'text', 'scope_type' => 'both', 'group' => 'contact', 'categories' => ['person', 'legal']],
            ['name' => 'website', 'title' => 'Website', 'type' => 'url', 'scope_type' => 'both', 'group' => 'contact', 'categories' => ['person', 'legal']],
        ];

        foreach ($definitions as $index => $definition) {
            $fieldGroup = (string) ($definition['group'] ?? 'identity');
            $groupId    = (int) ($groupIds[$fieldGroup] ?? 0);

            if ($groupId <= 0) {
                throw new \RuntimeException('Unable to resolve field group for definition: ' . (string) $definition['name']);
            }

            $fieldId = $this->createFieldIfMissing($db, $groupId, $definition, $index + 1);
            $this->syncFieldCategoryAssignments($db, $fieldId, (array) $definition['categories'], $categoryIds);
        }
    }

    private function ensureDefaultCategories(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $definitions = [
            'person' => [
                'title'                => 'Person',
                'alias'                => 'person',
                'display_name_pattern' => '{first-name} {last-name}',
            ],
            'legal' => [
                'title'                => 'Legal Entity',
                'alias'                => 'legal-entity',
                'display_name_pattern' => '{company-name}',
            ],
        ];

        $categoryIds = [];

        foreach ($definitions as $key => $definition) {
            $categoryId = $this->findCategoryIdByTitle($db, (string) $definition['title']);

            if ($categoryId <= 0) {
                $categoryId = $this->createDefaultCategory($db, $definition);
            }

            $this->updateCategoryPolicy(
                $db,
                $categoryId,
                (string) $definition['display_name_pattern']
            );

            $categoryIds[$key] = $categoryId;
        }

        return $categoryIds;
    }

    private function findCategoryIdByTitle(DatabaseInterface $db, string $title): int
    {
        $extension = self::COMPONENT_OPTION;

        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('title') . ' = :title')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':extension', $extension)
            ->bind(':title', $title);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    private function createDefaultCategory(DatabaseInterface $db, array $definition): int
    {
        $params = new Registry(
            [
                'display_name_pattern' => (string) $definition['display_name_pattern'],
                'user_link_policy'     => 'optional-many',
                'is_public_directory'  => 0,
            ]
        );

        /** @var \Joomla\CMS\Table\Category $category */
        $category = Table::getInstance('Category', 'Joomla\\CMS\\Table\\', ['dbo' => $db]);
        $category->setLocation(self::DEFAULT_CATEGORY_PARENT_ID, 'last-child');
        $category->bind(
            [
                'parent_id'   => self::DEFAULT_CATEGORY_PARENT_ID,
                'extension'   => self::COMPONENT_OPTION,
                'title'       => (string) $definition['title'],
                'alias'       => (string) $definition['alias'],
                'published'   => 1,
                'access'      => 1,
                'language'    => '*',
                'description' => '',
                'params'      => $params->toString('JSON'),
                'metadata'    => '{}',
            ]
        );

        if (!$category->check() || !$category->store(true)) {
            throw new \RuntimeException((string) $category->getError());
        }

        return (int) $category->id;
    }

    private function updateCategoryPolicy(
        DatabaseInterface $db,
        int $categoryId,
        string $displayNamePattern
    ): void {
        $extension = self::COMPONENT_OPTION;

        $query = $db->createQuery()
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('extension') . ' = :extension')
            ->bind(':id', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension);

        $db->setQuery($query);
        $paramsRaw = (string) $db->loadResult();
        $params    = new Registry($paramsRaw);

        $params->remove('profile_template');
        $params->remove('allowed_profile_type');

        if (trim((string) $params->get('display_name_pattern', '')) === '') {
            $params->set('display_name_pattern', $displayNamePattern);
        }

        $params->set('user_link_policy', $this->normalizeUserLinkPolicy((string) $params->get('user_link_policy', '')));

        if ((string) $params->get('is_public_directory', '') === '') {
            $params->set('is_public_directory', 0);
        }

        $paramsJson = $params->toString('JSON');

        $query = $db->createQuery()
            ->update($db->quoteName('#__categories'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('extension') . ' = :extension')
            ->bind(':params', $paramsJson)
            ->bind(':id', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension);

        $db->setQuery($query)->execute();
    }

    private function normalizeUserLinkPolicy(string $policy): string
    {
        $policy = trim($policy);

        if (!\in_array($policy, self::USER_LINK_POLICIES, true)) {
            return 'optional-many';
        }

        return $policy;
    }

    /**
     * Get existing field groups or create them if missing.
     *
     * @param DatabaseInterface $db Database object.
     *
     * @return array<string, int> Map of group key to field group ID.
     */
    private function getOrCreateFieldGroups(DatabaseInterface $db): array
    {
        return [
            'identity' => $this->getOrCreateFieldGroupByTitle($db, self::GROUP_IDENTITY_TITLE, 'Identity and anagraphic fields.'),
            'address'  => $this->getOrCreateFieldGroupByTitle($db, self::GROUP_ADDRESS_TITLE, 'Address-related fields.'),
            'contact'  => $this->getOrCreateFieldGroupByTitle($db, self::GROUP_CONTACT_TITLE, 'Contact fields.'),
        ];
    }

    /**
     * Get existing field group by title or create it if missing.
     *
     * @param DatabaseInterface $db Database object.
     * @param string $title Group title.
     * @param string $description Group description.
     *
     * @return int
     */
    private function getOrCreateFieldGroupByTitle(DatabaseInterface $db, string $title, string $description): int
    {
        $context = self::FIELDS_CONTEXT;

        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__fields_groups'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('title') . ' = :title')
            ->bind(':context', $context)
            ->bind(':title', $title);

        $db->setQuery($query);
        $groupId = (int) $db->loadResult();

        if ($groupId > 0) {
            return $groupId;
        }

        $group = new GroupTable($db);
        $group->bind(
            [
                'context'     => self::FIELDS_CONTEXT,
                'title'       => $title,
                'description' => $description,
                'state'       => 1,
                'access'      => 1,
                'language'    => '*',
                'ordering'    => 1,
                'params'      => [],
            ]
        );

        if (!$group->check() || !$group->store()) {
            throw new \RuntimeException((string) $group->getError());
        }

        return (int) $group->id;
    }

    /**
     * Create a field when no field with same context/name exists.
     *
     * @param DatabaseInterface $db Database object.
     * @param int $groupId Field group id.
     * @param array $definition Field definition.
     * @param int $ordering Default ordering.
     *
     * @return int
     */
    private function createFieldIfMissing(DatabaseInterface $db, int $groupId, array $definition, int $ordering): int
    {
        $context   = self::FIELDS_CONTEXT;
        $fieldName = (string) $definition['name'];
        $required  = \array_key_exists('required', $definition) ? (int) $definition['required'] : null;

        $query = $db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('group_id'), $db->quoteName('required'), $db->quoteName('type')])
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('name') . ' = :name')
            ->bind(':context', $context)
            ->bind(':name', $fieldName);

        $db->setQuery($query);

        $fieldRow = $db->loadObject();
        $fieldId  = (int) ($fieldRow->id ?? 0);

        if ($fieldId > 0) {
            $currentGroupId  = (int) ($fieldRow->group_id ?? 0);
            $currentRequired = (int) ($fieldRow->required ?? 0);
            $currentType     = trim((string) ($fieldRow->type ?? ''));
            $targetType      = trim((string) ($definition['type'] ?? ''));
            $requiresUpdate  = $currentGroupId !== $groupId
                || ($required !== null && $currentRequired !== $required)
                || ($targetType !== '' && $currentType !== $targetType);

            if ($requiresUpdate) {
                $query = $db->createQuery()
                    ->update($db->quoteName('#__fields'));

                if ($currentGroupId !== $groupId) {
                    $query->set($db->quoteName('group_id') . ' = :group_id')
                        ->bind(':group_id', $groupId, ParameterType::INTEGER);
                }

                if ($required !== null && $currentRequired !== $required) {
                    $query->set($db->quoteName('required') . ' = :required')
                        ->bind(':required', $required, ParameterType::INTEGER);
                }

                if ($targetType !== '' && $currentType !== $targetType) {
                    $query->set($db->quoteName('type') . ' = :type')
                        ->bind(':type', $targetType);
                }

                $query->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $fieldId, ParameterType::INTEGER);

                $db->setQuery($query)->execute();
            }

            return $fieldId;
        }

        $field = new FieldTable($db);

        $fieldparams = [
            'hint'         => '',
            'class'        => '',
            'showlabel'    => '1',
            'showon'       => '',
            'render_class' => '',
            'scope_type'   => (string) $definition['scope_type'],
        ];

        $field->bind(
            [
                'context'       => self::FIELDS_CONTEXT,
                'group_id'      => $groupId,
                'title'         => (string) $definition['title'],
                'label'         => (string) $definition['title'],
                'name'          => (string) $definition['name'],
                'type'          => (string) $definition['type'],
                'state'         => 1,
                'required'      => $required ?? 0,
                'default_value' => '',
                'access'        => 1,
                'language'      => '*',
                'ordering'      => $ordering,
                'description'   => '',
                'params'        => [
                    'showlabel' => '1',
                ],
                'fieldparams' => $fieldparams,
            ]
        );

        if (!$field->check() || !$field->store()) {
            throw new \RuntimeException((string) $field->getError());
        }

        return (int) $field->id;
    }

    private function normalizeFieldDefaultValues(DatabaseInterface $db): void
    {
        $context = self::FIELDS_CONTEXT;
        $empty   = '';

        $query = $db->createQuery()
            ->update($db->quoteName('#__fields'))
            ->set($db->quoteName('default_value') . ' = :default_value')
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('default_value') . ' IS NULL')
            ->bind(':default_value', $empty)
            ->bind(':context', $context);

        $db->setQuery($query)->execute();
    }

    private function syncFieldCategoryAssignments(
        DatabaseInterface $db,
        int $fieldId,
        array $categoryKeys,
        array $categoryIds
    ): void {
        $targetCategoryIds = [];

        foreach ($categoryKeys as $categoryKey) {
            $categoryId = (int) ($categoryIds[$categoryKey] ?? 0);

            if ($categoryId > 0) {
                $targetCategoryIds[] = $categoryId;
            }
        }

        $targetCategoryIds = array_values(array_unique($targetCategoryIds));

        if ($targetCategoryIds === []) {
            return;
        }

        $query = $db->createQuery()
            ->select($db->quoteName('category_id'))
            ->from($db->quoteName('#__fields_categories'))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->bind(':field_id', $fieldId, ParameterType::INTEGER);

        $db->setQuery($query);
        $existingCategoryIds = array_map('intval', (array) $db->loadColumn());

        foreach ($existingCategoryIds as $existingCategoryId) {
            if ($existingCategoryId === 0 || !\in_array($existingCategoryId, $targetCategoryIds, true)) {
                $query = $db->createQuery()
                    ->delete($db->quoteName('#__fields_categories'))
                    ->where($db->quoteName('field_id') . ' = :field_id')
                    ->where($db->quoteName('category_id') . ' = :category_id')
                    ->bind(':field_id', $fieldId, ParameterType::INTEGER)
                    ->bind(':category_id', $existingCategoryId, ParameterType::INTEGER);

                $db->setQuery($query)->execute();
            }
        }

        foreach ($targetCategoryIds as $targetCategoryId) {
            if (\in_array($targetCategoryId, $existingCategoryIds, true)) {
                continue;
            }

            $query = $db->createQuery()
                ->insert($db->quoteName('#__fields_categories'))
                ->columns([$db->quoteName('field_id'), $db->quoteName('category_id')])
                ->values(':field_id, :category_id')
                ->bind(':field_id', $fieldId, ParameterType::INTEGER)
                ->bind(':category_id', $targetCategoryId, ParameterType::INTEGER);

            $db->setQuery($query)->execute();
        }
    }

    /**
     * Ensure profiles table exists even on installations affected by old SQL manifest paths.
     *
     * @return void
     */
    private function ensureProfilesTableExists(): void
    {
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = strtolower((string) $db->replacePrefix('#__joomlalabs_profiles'));
        $tables    = array_map('strtolower', (array) $db->getTableList());

        if (\in_array($tableName, $tables, true)) {
            return;
        }

        $sqlCandidates = [
            __DIR__ . '/sql/install.mysql.utf8.sql',
            __DIR__ . '/admin/sql/install.mysql.utf8.sql',
        ];
        $sqlPath = null;

        foreach ($sqlCandidates as $candidate) {
            if (is_file($candidate)) {
                $sqlPath = $candidate;
                break;
            }
        }

        if ($sqlPath === null) {
            throw new \RuntimeException('Install SQL file not found in expected locations.');
        }

        $sql = file_get_contents($sqlPath);

        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('Install SQL file is empty or unreadable.');
        }

        $statement = rtrim((string) $db->replacePrefix(trim($sql)), ";\r\n\t ");

        if ($statement === '') {
            throw new \RuntimeException('Resolved SQL statement is empty.');
        }

        $db->setQuery($statement)->execute();
    }

    /**
     * Drop legacy core columns replaced by fields.
     *
     * @return void
     */
    private function pruneLegacyCoreNameColumns(): void
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $columns = array_change_key_case((array) $db->getTableColumns('#__joomlalabs_profiles', false), CASE_LOWER);

        foreach (['first_name', 'last_name', 'company_name'] as $legacyColumn) {
            if (!\array_key_exists($legacyColumn, $columns)) {
                continue;
            }

            $query = 'ALTER TABLE ' . $db->quoteName('#__joomlalabs_profiles')
                . ' DROP COLUMN ' . $db->quoteName($legacyColumn);

            $db->setQuery($query)->execute();
        }
    }

    /**
     * Check whether a database table exists.
     *
     * @param DatabaseInterface $db Database object.
     * @param string $tableName Table name with prefix placeholder.
     *
     * @return bool
     */
    private function tableExists(DatabaseInterface $db, string $tableName): bool
    {
        $resolvedName = strtolower((string) $db->replacePrefix($tableName));
        $tables       = array_map('strtolower', (array) $db->getTableList());

        return \in_array($resolvedName, $tables, true);
    }

    /**
     * Normalize administrator submenu links to canonical index.php routes.
     *
     * @return void
     */
    private function ensureAdminMenuLinksCanonical(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $queries = [
            "UPDATE `#__menu`
SET `link` = REPLACE(REPLACE(`link`, '&amp;', '&'), 'index.php?index.php?', 'index.php?')
WHERE `client_id` = 1
  AND (
    `link` LIKE '%com_joomlalabs_profiles%'
    OR `link` LIKE '%extension=com_joomlalabs_profiles%'
        OR `link` LIKE '%context=com_joomlalabs_profiles.profile%'
        OR `link` LIKE '%context=com_joomlalabs_profiles.record%'
  )",
            "UPDATE `#__menu`
SET `link` = CONCAT('index.php?', `link`)
WHERE `client_id` = 1
  AND `link` LIKE 'option=%'
  AND (
    `link` LIKE '%com_joomlalabs_profiles%'
    OR `link` LIKE '%extension=com_joomlalabs_profiles%'
        OR `link` LIKE '%context=com_joomlalabs_profiles.profile%'
        OR `link` LIKE '%context=com_joomlalabs_profiles.record%'
  )",
            "UPDATE `#__menu`
SET `link` = 'index.php?option=com_joomlalabs_profiles'
WHERE `client_id` = 1
  AND (
    (`title` = 'COM_JOOMLALABS_PROFILES_MENU_PROFILES' AND `link` LIKE '%com_joomlalabs_profiles%')
    OR (`link` IN ('option=com_joomlalabs_profiles', 'index.php?option=com_joomlalabs_profiles', 'index.php?index.php?option=com_joomlalabs_profiles'))
    OR (
      `link` LIKE '%com_joomlalabs_profiles%'
      AND `link` NOT LIKE '%com_categories%'
      AND `link` NOT LIKE '%com_fields%'
    )
  )",
            "UPDATE `#__menu`
SET `link` = 'index.php?option=com_categories&view=categories&extension=com_joomlalabs_profiles'
WHERE `client_id` = 1
  AND (
    (`title` = 'JCATEGORIES' AND `link` LIKE '%extension=com_joomlalabs_profiles%')
    OR (`link` LIKE '%com_categories%' AND `link` LIKE '%extension=com_joomlalabs_profiles%')
  )",
            "UPDATE `#__menu`
        SET `link` = 'index.php?option=com_fields&view=groups&context=com_joomlalabs_profiles.record'
WHERE `client_id` = 1
  AND (
        (`title` = 'COM_JOOMLALABS_PROFILES_MENU_FIELD_GROUPS' AND (`link` LIKE '%context=com_joomlalabs_profiles.profile%' OR `link` LIKE '%context=com_joomlalabs_profiles.record%'))
        OR (
            `link` LIKE '%view=groups%'
            AND (`link` LIKE '%context=com_joomlalabs_profiles.profile%' OR `link` LIKE '%context=com_joomlalabs_profiles.record%')
        )
  )",
            "UPDATE `#__menu`
        SET `link` = 'index.php?option=com_fields&view=fields&context=com_joomlalabs_profiles.record'
WHERE `client_id` = 1
  AND (
        (`title` = 'JGLOBAL_FIELDS' AND (`link` LIKE '%context=com_joomlalabs_profiles.profile%' OR `link` LIKE '%context=com_joomlalabs_profiles.record%'))
        OR (
            `link` LIKE '%option=com_fields%'
            AND (`link` LIKE '%context=com_joomlalabs_profiles.profile%' OR `link` LIKE '%context=com_joomlalabs_profiles.record%')
            AND `link` NOT LIKE '%view=groups%'
        )
  )",
        ];

        foreach ($queries as $query) {
            $db->setQuery($query)->execute();
        }
    }
}
