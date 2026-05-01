<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  User.joomlalabs_profiles_autoprofile
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Plugin\User\ProfilesAutoProfile\Extension;

use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use JoomlaLabs\Component\Profiles\Administrator\Model\ProfileModel;

\defined('_JEXEC') or die;

final class ProfilesAutoProfile extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private const USER_LINK_POLICIES = [
        'optional-many',
        'required-many',
        'optional-single',
        'required-single',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave' => 'onUserAfterSave',
        ];
    }

    public function onUserAfterSave(AfterSaveEvent $event): void
    {
        $user    = $event->getUser();
        $isNew   = $event->getIsNew();
        $success = $event->getSavingResult();

        if (!$success) {

            return;
        }

        $userId = (int) ($user['id'] ?? 0);

        if (!$userId) {

            return;
        }

        $syncOnUserEdit                   = (bool) $this->params->get('sync_on_user_edit', 0);
        $createOnEditIfMissing            = (bool) $this->params->get('create_on_user_edit_if_missing', 0);
        $skipAutoCreateIfAnyProfileExists = (bool) $this->params->get('skip_if_profile_exists', 1);

        if (!$isNew && !$syncOnUserEdit) {

            return;
        }

        $userDisplayName = trim((string) ($user['name'] ?? $user['username'] ?? ('User ' . $userId)));

        if ($userDisplayName === '') {
            $userDisplayName = 'User ' . $userId;
        }

        $published = (int) $this->params->get('auto_publish', 0);
        $catid     = (int) $this->params->get('default_category_id', 0);

        if ($catid <= 0) {

            return;
        }

        $categoryPolicy = $this->getCategoryPolicy($catid);

        if ($categoryPolicy === null) {

            return;
        }

        $existingProfile = null;

        if ($isNew) {
            if (str_ends_with((string) $categoryPolicy['user_link_policy'], 'single') && $this->hasProfileForUserInCategory($userId, $catid)) {

                return;
            }
        } else {
            $existingProfile = $this->loadProfileForUserInCategory($userId, $catid);

            if ($existingProfile === null && !$createOnEditIfMissing) {
                return;
            }
        }

        $userCustomFields = $this->resolveUserCustomFieldTokenMap($user, $userId);

        $displayNamePatternOverride = trim((string) $this->params->get('display_name_pattern_override', ''));
        $displayNameOverride        = '';

        if ($displayNamePatternOverride !== '') {
            $displayNameOverride = $this->resolveDisplayNameFromOverride(
                $displayNamePatternOverride,
                $user,
                $userDisplayName,
                $userCustomFields
            );

            if ($displayNameOverride === '') {
                $displayNameOverride = $userDisplayName;
            }
        }

        $profileCustomFieldMap = $this->resolveProfileCustomFieldAutoMap($user, $userDisplayName, $userCustomFields);

        if ($profileCustomFieldMap !== []) {
            $profileCustomFieldMap = $this->filterProfileCustomFieldMapByCategory($profileCustomFieldMap, $catid);
        }

        $createMode = $isNew || (!$isNew && $existingProfile === null);

        // Guardrail: when enabled, never auto-create if user already has at least one profile.
        if ($createMode && $skipAutoCreateIfAnyProfileExists && $this->hasProfileForUser($userId)) {
            return;
        }

        if ($createMode) {
            $accessValue   = (int) $categoryPolicy['access'];
            $languageValue = (string) $categoryPolicy['language'];

            $saveData = [
                'id'          => 0,
                'catid'       => $catid,
                'published'   => $published,
                'access'      => $accessValue,
                'language'    => $languageValue,
                'user_id'     => $userId,
                'created_by'  => $userId,
                'modified_by' => $userId,
                'alias'       => '',
            ];
        } else {
            $existingId = (int) ($existingProfile['id'] ?? 0);

            if ($existingId <= 0) {
                return;
            }

            $saveData = [
                'id'          => $existingId,
                'catid'       => (int) ($existingProfile['catid'] ?? $catid),
                'user_id'     => $userId,
                'modified_by' => $userId,
            ];

            $existingAlias = trim((string) ($existingProfile['alias'] ?? ''));

            if ($existingAlias !== '') {
                $saveData['alias'] = $existingAlias;
            }
        }

        if ($displayNameOverride !== '') {
            $saveData['_display_name_override'] = $displayNameOverride;
        }

        if ($profileCustomFieldMap !== []) {
            $saveData['com_fields'] = $profileCustomFieldMap;
        }

        $this->createProfileViaComponent($saveData);
    }

    private function createProfileViaComponent(array $saveData): bool
    {
        try {
            $app       = Factory::getApplication();
            $component = $app->bootComponent('com_joomlalabs_profiles');

            if (!$component instanceof MVCFactoryServiceInterface) {

                return false;
            }

            $model = $component->getMVCFactory()->createModel('Profile', 'Administrator', ['ignore_request' => true]);

            if (!$model instanceof ProfileModel) {

                return false;
            }

            $result = (bool) $model->save($saveData);

            return $result;
        } catch (\Throwable $exception) {
            // Do not block user registration if profile auto-creation fails.
            Log::add(
                'plg_user_joomlalabs_profiles_autoprofile: profile auto-creation failed for user_id '
                    . (int) ($saveData['user_id'] ?? 0)
                    . ' — ' . $exception->getMessage(),
                Log::WARNING,
                'plg_user_joomlalabs_profiles_autoprofile'
            );

            return false;
        }
    }

    private function resolveDisplayNameFromOverride(string $pattern, array $user, string $fallbackDisplayName, array $userCustomFields = []): string
    {
        $coreTokens = $this->buildCoreUserTokenMap($user, $fallbackDisplayName);

        $displayName = preg_replace_callback(
            '/\{([a-z0-9_:-]+)\}/i',
            function (array $matches) use ($coreTokens, $userCustomFields): string {
                $rawToken = strtolower(trim((string) ($matches[1] ?? '')));

                if ($rawToken === '') {
                    return '';
                }

                $normalizedToken = str_replace('-', '_', $rawToken);

                if (\array_key_exists($normalizedToken, $coreTokens)) {
                    return (string) $coreTokens[$normalizedToken];
                }

                if (str_starts_with($rawToken, 'usercf:')) {
                    $fieldToken = substr($rawToken, 7);
                    $fieldKey   = $this->normalizeFieldNameKey($fieldToken);

                    if ($fieldKey !== '') {
                        return (string) ($userCustomFields[$fieldKey] ?? '');
                    }
                }

                return '';
            },
            $pattern
        );

        return preg_replace('/\s+/', ' ', trim((string) $displayName)) ?? trim((string) $displayName);
    }

    private function buildCoreUserTokenMap(array $user, string $fallbackDisplayName): array
    {
        $name     = trim((string) ($user['name'] ?? $fallbackDisplayName));
        $username = trim((string) ($user['username'] ?? ''));
        $email    = trim((string) ($user['email'] ?? ''));

        if ($name === '') {
            $name = $fallbackDisplayName;
        }

        return [
            'name'       => $name,
            'username'   => $username,
            'email'      => $email,
            'login_name' => $username,
            'loginname'  => $username,
        ];
    }

    private function resolveProfileCustomFieldAutoMap(array $user, string $fallbackDisplayName, array $userCustomFields): array
    {
        $rawAutoMap  = $this->params->get('custom_field_automap', []);
        $autoMapRows = $this->normalizeAutoMapRows($rawAutoMap);

        if ($autoMapRows === []) {
            $storedAutoMap = $this->loadStoredAutoMapRows();

            if ($storedAutoMap !== null) {
                $autoMapRows = $this->normalizeAutoMapRows($storedAutoMap);
            }
        }

        if ($autoMapRows === []) {
            return [];
        }

        $coreTokens = $this->buildCoreUserTokenMap($user, $fallbackDisplayName);
        $mapped     = [];

        foreach ($autoMapRows as $index => $row) {
            $source = trim((string) ($row['user_source'] ?? ''));
            $target = trim((string) ($row['profile_field'] ?? ''));

            if ($source === '' || $target === '') {

                continue;
            }

            $value = $this->resolveAutoMapSourceValue($source, $coreTokens, $userCustomFields);

            if ($value === null) {

                continue;
            }

            if (ctype_digit($target)) {
                $mapped[(int) $target] = $value;

                continue;
            }

            $mapped[$target] = $value;

        }

        return $mapped;
    }

    private function normalizeAutoMapRows($rawRows): array
    {
        $toArray = static function ($value) {
            if ($value instanceof Registry) {
                return $value->toArray();
            }

            if (\is_object($value)) {
                return (array) $value;
            }

            if (\is_string($value)) {
                $decoded = json_decode($value, true);

                if (\is_array($decoded)) {
                    return $decoded;
                }
            }

            return $value;
        };

        $rawRows = $toArray($rawRows);

        if (\is_array($rawRows) && isset($rawRows['custom_field_automap'])) {
            $rawRows = $toArray($rawRows['custom_field_automap']);
        }

        if (!\is_array($rawRows)) {
            return [];
        }

        $rows  = [];
        $stack = [$rawRows];

        while ($stack !== []) {
            $current = array_pop($stack);
            $current = $toArray($current);

            if (!\is_array($current)) {
                continue;
            }

            $source = trim((string) ($current['user_source'] ?? ''));
            $target = trim((string) ($current['profile_field'] ?? ''));

            if ($source !== '' || $target !== '') {
                $rows[] = [
                    'user_source'   => $source,
                    'profile_field' => $target,
                ];

                continue;
            }

            foreach (array_reverse($current, true) as $child) {
                $stack[] = $child;
            }
        }

        return $rows;
    }

    private function loadStoredAutoMapRows()
    {
        $db      = $this->getDatabase();
        $type    = 'plugin';
        $folder  = 'user';
        $element = 'joomlalabs_profiles_autoprofile';

        $query = $db->createQuery()
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':type', $type)
            ->where($db->quoteName('folder') . ' = :folder')
            ->bind(':folder', $folder)
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element)
            ->setLimit(1);

        $db->setQuery($query);

        try {
            $paramsText = (string) $db->loadResult();
        } catch (\RuntimeException) {
            return null;
        }

        if ($paramsText === '') {
            return null;
        }

        $params = new Registry($paramsText);

        return $params->get('custom_field_automap', null);
    }

    private function resolveAutoMapSourceValue(string $source, array $coreTokens, array $userCustomFields): ?string
    {
        $source = trim($source);

        if ($source === '') {
            return null;
        }

        if (str_starts_with($source, '{') && str_ends_with($source, '}')) {
            $source = substr($source, 1, -1);
        }

        $source = strtolower(trim($source));

        if ($source === '') {
            return null;
        }

        $normalized = str_replace('-', '_', $source);

        if (\array_key_exists($normalized, $coreTokens)) {
            $value = trim((string) $coreTokens[$normalized]);

            return $value !== '' ? $value : null;
        }

        if (str_starts_with($normalized, 'usercf:')) {
            $normalized = substr($normalized, 7);
        }

        $fieldKey = $this->normalizeFieldNameKey($normalized);

        if ($fieldKey === '') {
            return null;
        }

        $value = trim((string) ($userCustomFields[$fieldKey] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function resolveUserCustomFieldTokenMap(array $user, int $userId): array
    {
        $payloadValues = $this->extractUserCustomFieldTokenMapFromData($user['com_fields'] ?? []);

        $jform       = Factory::getApplication()->getInput()->get('jform', [], 'array');
        $inputValues = $this->extractUserCustomFieldTokenMapFromData($jform['com_fields'] ?? []);

        $storedValues = $userId > 0 ? $this->loadUserCustomFieldTokenMap($userId) : [];

        $resolved = array_replace($payloadValues, $inputValues, $storedValues);

        return $resolved;
    }

    private function extractUserCustomFieldTokenMapFromData($rawValues): array
    {
        if ($rawValues instanceof Registry) {
            $rawValues = $rawValues->toArray();
        } elseif (\is_object($rawValues)) {
            $rawValues = (array) $rawValues;
        }

        if (!\is_array($rawValues) || $rawValues === []) {
            return [];
        }

        $valuesByName = [];
        $valuesById   = [];

        foreach ($rawValues as $key => $value) {
            $normalizedValue = $this->normalizeFlexibleFieldValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            if (\is_int($key) || ctype_digit((string) $key)) {
                $valuesById[(int) $key] = $normalizedValue;

                continue;
            }

            $normalizedKey = $this->normalizeFieldNameKey((string) $key);

            if ($normalizedKey !== '') {
                $valuesByName[$normalizedKey] = $normalizedValue;
            }
        }

        if ($valuesById === []) {
            return $valuesByName;
        }

        $nameMap = $this->mapUserFieldIdsToNames(array_keys($valuesById));

        foreach ($valuesById as $fieldId => $value) {
            $fieldName = $this->normalizeFieldNameKey((string) ($nameMap[$fieldId] ?? ''));

            if ($fieldName !== '') {
                $valuesByName[$fieldName] = $value;
            }
        }

        return $valuesByName;
    }

    private function mapUserFieldIdsToNames(array $fieldIds): array
    {
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn (int $id): bool => $id > 0)));

        if ($fieldIds === []) {
            return [];
        }

        $db      = $this->getDatabase();
        $context = 'com_users.user';
        $query   = $db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('name')])
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context')
            ->bind(':context', $context)
            ->whereIn($db->quoteName('id'), $fieldIds);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $map[(int) ($row->id ?? 0)] = (string) ($row->name ?? '');
        }

        return $map;
    }

    private function normalizeFlexibleFieldValue($value): string
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

    private function filterProfileCustomFieldMapByCategory(array $map, int $catid): array
    {
        if ($map === [] || $catid <= 0) {
            return $map;
        }

        $db      = $this->getDatabase();
        $context = 'com_joomlalabs_profiles.record';
        $query   = $db->createQuery()
            ->select([$db->quoteName('f.id'), $db->quoteName('f.name')])
            ->from($db->quoteName('#__fields', 'f'))
            ->join(
                'INNER',
                $db->quoteName('#__fields_categories', 'fc')
                . ' ON ' . $db->quoteName('fc.field_id') . ' = ' . $db->quoteName('f.id')
            )
            ->where($db->quoteName('f.context') . ' = :context')
            ->bind(':context', $context)
            ->where($db->quoteName('f.state') . ' = 1')
            ->where($db->quoteName('fc.category_id') . ' = :category_id')
            ->bind(':category_id', $catid, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException) {
            return [];
        }

        $allowedIds   = [];
        $allowedNames = [];

        foreach ($rows as $row) {
            $fieldId   = (int) ($row->id ?? 0);
            $fieldName = trim((string) ($row->name ?? ''));

            if ($fieldId > 0) {
                $allowedIds[$fieldId] = true;
            }

            if ($fieldName !== '') {
                $allowedNames[$this->normalizeFieldNameKey($fieldName)] = true;
            }
        }

        if ($allowedIds === [] && $allowedNames === []) {

            return [];
        }

        $filtered = [];

        foreach ($map as $key => $value) {
            if (\is_int($key) || ctype_digit((string) $key)) {
                $fieldId = (int) $key;

                if (isset($allowedIds[$fieldId])) {
                    $filtered[$fieldId] = $value;

                }

                continue;
            }

            $normalizedName = $this->normalizeFieldNameKey((string) $key);

            if ($normalizedName !== '' && isset($allowedNames[$normalizedName])) {
                $filtered[(string) $key] = $value;

            }

        }

        return $filtered;
    }

    private function loadUserCustomFieldTokenMap(int $userId): array
    {
        $db      = $this->getDatabase();
        $context = 'com_users.user';
        $itemId  = (string) $userId;

        $query = $db->createQuery()
            ->select([$db->quoteName('f.name'), $db->quoteName('fv.value')])
            ->from($db->quoteName('#__fields_values', 'fv'))
            ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('fv.field_id'))
            ->where($db->quoteName('f.context') . ' = :context')
            ->whereIn($db->quoteName('f.state'), [0, 1], ParameterType::INTEGER)
            ->where($db->quoteName('fv.item_id') . ' = :item_id')
            ->bind(':context', $context)
            ->bind(':item_id', $itemId);

        $db->setQuery($query);

        try {
            $rows = (array) $db->loadObjectList();
        } catch (\RuntimeException) {
            return [];
        }

        $tokens = [];

        foreach ($rows as $row) {
            $fieldKey   = $this->normalizeFieldNameKey((string) ($row->name ?? ''));
            $fieldValue = trim((string) ($row->value ?? ''));

            if ($fieldKey === '' || $fieldValue === '') {
                continue;
            }

            if (!isset($tokens[$fieldKey])) {
                $tokens[$fieldKey] = $fieldValue;

                continue;
            }

            if (!str_contains(' ' . $tokens[$fieldKey] . ' ', ' ' . $fieldValue . ' ')) {
                $tokens[$fieldKey] .= ' ' . $fieldValue;
            }
        }

        return $tokens;
    }

    private function hasProfileForUser(int $userId): bool
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->bind(':user_id', $userId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    private function hasProfileForUserInCategory(int $userId, int $catid): bool
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->where($db->quoteName('catid') . ' = :catid')
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    private function loadProfileForUserInCategory(int $userId, int $catid): ?array
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select(
                [
                    $db->quoteName('id'),
                    $db->quoteName('catid'),
                    $db->quoteName('alias'),
                ]
            )
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->where($db->quoteName('catid') . ' = :catid')
            ->bind(':user_id', $userId, ParameterType::INTEGER)
            ->bind(':catid', $catid, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' DESC')
            ->setLimit(1);

        $db->setQuery($query);

        try {
            $row = $db->loadAssoc();
        } catch (\RuntimeException) {
            return null;
        }

        return \is_array($row) ? $row : null;
    }

    private function getCategoryPolicy(int $catid): ?array
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select(
                [
                    $db->quoteName('extension'),
                    $db->quoteName('access'),
                    $db->quoteName('language'),
                    $db->quoteName('params'),
                    $db->quoteName('alias'),
                ]
            )
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :catid')
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);
        $category = $db->loadObject();

        if (!$category || (string) $category->extension !== 'com_joomlalabs_profiles') {
            return null;
        }

        $params = new Registry((string) $category->params);

        $displayNamePattern = (string) $params->get('display_name_pattern', '');
        $userLinkPolicy     = $this->normalizeUserLinkPolicy((string) $params->get('user_link_policy', 'optional-many'));

        $profileTypeKey = $this->resolveProfileTypeKey($catid, (string) ($category->alias ?? ''), $params);

        return [
            'display_name_pattern' => $displayNamePattern,
            'user_link_policy'     => $userLinkPolicy,
            'profile_type_key'     => $profileTypeKey,
            'access'               => max(1, (int) $category->access),
            'language'             => (string) ($category->language ?: '*'),
        ];
    }

    private function normalizeUserLinkPolicy(string $policy): string
    {
        $policy = trim($policy);

        if (!\in_array($policy, self::USER_LINK_POLICIES, true)) {
            return 'optional-many';
        }

        return $policy;
    }

    private function resolveDisplayNamePattern(string $pattern): string
    {
        return trim($pattern);
    }

    private function resolveProfileTypeKey(int $catid, string $categoryAlias, Registry $params): string
    {
        foreach (['profile_type_key', 'profile_type', 'allowed_profile_type'] as $paramName) {
            $configuredKey = $this->normalizeProfileTypeKey((string) $params->get($paramName, ''));

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

    private function extractPlaceholders(string $pattern): array
    {
        if ($pattern === '') {
            return [];
        }

        preg_match_all('/\{([a-z0-9_-]+)\}/i', $pattern, $matches);

        $placeholders = [];

        foreach (($matches[1] ?? []) as $placeholder) {
            $normalized = $this->normalizeFieldNameKey((string) $placeholder);

            if ($normalized !== '') {
                $placeholders[$normalized] = $normalized;
            }
        }

        return array_values($placeholders);
    }

    private function normalizeFieldNameKey(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return '';
        }

        return str_replace('-', '_', $name);
    }

    private function buildTokenValueMap(array $user, string $userDisplayName, string $username, array $placeholders): array
    {
        $nameParts = preg_split('/\s+/', trim($userDisplayName), 2) ?: [];

        $values = [
            'id'            => (string) ($user['id'] ?? ''),
            'name'          => $userDisplayName,
            'display_name'  => $userDisplayName,
            'full_name'     => $userDisplayName,
            'username'      => $username,
            'email'         => trim((string) ($user['email'] ?? '')),
            'user_name'     => $userDisplayName,
            'user_username' => $username,
            'user_email'    => trim((string) ($user['email'] ?? '')),
        ];

        foreach ($user as $key => $value) {
            if (\is_array($value) || \is_object($value)) {
                continue;
            }

            $normalizedKey = $this->normalizeFieldNameKey((string) $key);

            if ($normalizedKey !== '') {
                $values[$normalizedKey] = trim((string) $value);
            }
        }

        foreach ($placeholders as $placeholder) {
            $placeholderValue = trim((string) ($values[$placeholder] ?? ''));

            if ($placeholderValue !== '') {
                continue;
            }

            if (str_starts_with($placeholder, 'user_')) {
                $unprefixed = substr($placeholder, 5);

                if ($unprefixed !== '') {
                    $candidate = trim((string) ($values[$unprefixed] ?? ''));

                    if ($candidate !== '') {
                        $values[$placeholder] = $candidate;
                        continue;
                    }
                }
            }

            $prefixedKey = 'user_' . $placeholder;
            $candidate   = trim((string) ($values[$prefixedKey] ?? ''));

            if ($candidate !== '') {
                $values[$placeholder] = $candidate;

                continue;
            }

            $heuristic = $this->resolveHeuristicTokenValue($placeholder, $nameParts, $userDisplayName, $username, trim((string) ($user['email'] ?? '')));

            if ($heuristic !== '') {
                $values[$placeholder] = $heuristic;
            }
        }

        return $values;
    }

    private function resolveHeuristicTokenValue(string $placeholder, array $nameParts, string $userDisplayName, string $username, string $email): string
    {
        $tokens   = array_values(array_filter(explode('_', $placeholder), static fn (string $token): bool => $token !== ''));
        $hasToken = static fn (string $token): bool => \in_array($token, $tokens, true);

        if ($hasToken('email') && $email !== '') {
            return $email;
        }

        if ($hasToken('username') || $hasToken('login') || $hasToken('account')) {
            return $username;
        }

        if ($hasToken('first') && $hasToken('name')) {
            return trim((string) ($nameParts[0] ?? $username));
        }

        if (($hasToken('last') || $hasToken('family') || $hasToken('surname')) && $hasToken('name')) {
            return trim((string) ($nameParts[1] ?? ''));
        }

        if ($hasToken('name')) {
            return trim($userDisplayName);
        }

        return '';
    }

    private function composeDisplayNameFromValues(array $values, array $preferredKeys = []): string
    {
        $parts = [];

        foreach ($preferredKeys as $preferredKey) {
            $normalizedKey = $this->normalizeFieldNameKey($preferredKey);
            $candidate     = trim((string) ($values[$normalizedKey] ?? ''));

            if ($candidate === '' || \in_array($candidate, $parts, true)) {
                continue;
            }

            $parts[] = $candidate;

            if (\count($parts) >= 3) {
                break;
            }
        }

        if ($parts === []) {
            foreach ($values as $candidate) {
                $candidate = trim((string) $candidate);

                if ($candidate === '' || \in_array($candidate, $parts, true)) {
                    continue;
                }

                $parts[] = $candidate;

                if (\count($parts) >= 3) {
                    break;
                }
            }
        }

        $displayName = implode(' ', $parts);

        return preg_replace('/\s+/', ' ', trim($displayName)) ?? trim($displayName);
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

        return preg_replace('/\s+/', ' ', trim((string) $displayName)) ?? trim((string) $displayName);
    }
}
