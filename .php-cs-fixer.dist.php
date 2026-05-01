<?php
/**
 * @package    pkg_joomlalabs_profiles
 *
 * @author     Joomla!LABS <info@joomlalabs.com>
 * @copyright  (C) 2015 - 2026 Joomla!LABS. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://joomlalabs.com
 * @since      0.1.68
 */

/**
 * This is the configuration file for php-cs-fixer.
 *
 * @link https://github.com/FriendsOfPHP/PHP-CS-Fixer
 * @link https://mlocati.github.io/php-cs-fixer-configurator/#version:3.0
 *
 * To run a quick dry run to see the files that would be modified:
 *
 *        php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run
 *
 * To run a full check, with automated fixing of each problem:
 *
 *        php-cs-fixer fix --config=.php-cs-fixer.dist.php
 */

$finderClass = 'PhpCsFixer\\Finder';
$configClass = 'PhpCsFixer\\Config';

$finder = $finderClass::create()
    ->in(
        [
            __DIR__ . '/components/com_joomlalabs_profiles/admin/services',
            __DIR__ . '/components/com_joomlalabs_profiles/admin/src',
            __DIR__ . '/components/com_joomlalabs_profiles/src',
            __DIR__ . '/plugins/actionlog/joomlalabs_profiles/services',
            __DIR__ . '/plugins/actionlog/joomlalabs_profiles/src',
            __DIR__ . '/plugins/privacy/joomlalabs_profiles/services',
            __DIR__ . '/plugins/privacy/joomlalabs_profiles/src',
            __DIR__ . '/plugins/user/joomlalabs_profiles_autoprofile/services',
            __DIR__ . '/plugins/user/joomlalabs_profiles_autoprofile/src',
        ]
    )
    ->append(
        [
            __DIR__ . '/components/com_joomlalabs_profiles/admin/script.php',
            __DIR__ . '/components/com_joomlalabs_profiles/script.php',
            __DIR__ . '/packages/pkg_joomlalabs_profiles/script.php',
        ]
    )
    ->notPath('tmpl')
    ->notPath('layouts')
    ->notPath('media');

$config = new $configClass();
$config
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setUsingCache(false)
    ->setRules(
        [
            '@PSR12'                                           => true,
            'array_syntax'                                     => ['syntax' => 'short'],
            'blank_line_after_opening_tag'                     => true,
            'no_leading_import_slash'                          => true,
            'blank_line_after_namespace'                       => true,
            'modernize_strpos'                                 => true,
            'constant_case'                                    => ['case' => 'lower'],
            'phpdoc_add_missing_param_annotation'              => true,
            'phpdoc_align'                                     => ['align' => 'left'],
            'phpdoc_order'                                     => true,
            'phpdoc_no_empty_return'                           => false,
            'phpdoc_scalar'                                    => true,
            'phpdoc_summary'                                   => true,
            'no_extra_blank_lines'                             => true,
            'no_trailing_comma_in_singleline'                  => true,
            'trailing_comma_in_multiline'                      => ['elements' => ['arrays']],
            'binary_operator_spaces'                           => ['operators' => ['=>' => 'align_single_space_minimal', '=' => 'align']],
            'no_break_comment'                                 => ['comment_text' => 'No break'],
            'no_unused_imports'                                => true,
            'global_namespace_import'                          => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
            'ordered_imports'                                  => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
            'no_useless_else'                                  => true,
            'native_function_invocation'                       => ['include' => ['@compiler_optimized']],
            'nullable_type_declaration_for_default_null_value' => true,
            'no_unneeded_control_parentheses'                  => true,
            'combine_consecutive_issets'                       => true,
            'combine_consecutive_unsets'                       => true,
            'no_useless_sprintf'                               => true,
        ]
    )
    ->setFinder($finder);

return $config;