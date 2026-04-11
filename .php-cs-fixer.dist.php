<?php

/**
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

$finder = PhpCsFixer\Finder::create()
    ->in(
        [
            __DIR__ . '/src',
            __DIR__ . '/plg_task_cwmscripture/src',
            __DIR__ . '/tests',
        ]
    )
    ->append(
        [
            __DIR__ . '/script.php',
            __DIR__ . '/services/provider.php',
            __DIR__ . '/plg_task_cwmscripture/script.php',
            __DIR__ . '/plg_task_cwmscripture/services/provider.php',
        ]
    )
    ->notPath('/tmpl/')
    ->notPath('/layouts/')
    ->notPath('#vendor/#');

$config = new PhpCsFixer\Config();
$config
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setUsingCache(false)
    ->setRules(
        [
            '@PSR12'                          => true,
            'array_syntax'                    => ['syntax' => 'short'],
            'no_trailing_comma_in_singleline' => true,
            'trailing_comma_in_multiline'     => ['elements' => ['arrays']],
            'binary_operator_spaces'          => [
                'operators' => ['=>' => 'align_single_space_minimal', '=' => 'align', '??=' => 'align'],
            ],
            'no_break_comment'        => ['comment_text' => 'No break'],
            'no_unused_imports'       => true,
            'global_namespace_import' => [
                'import_classes'   => false,
                'import_constants' => false,
                'import_functions' => false,
            ],
            'ordered_imports' => [
                'imports_order'  => ['class', 'function', 'const'],
                'sort_algorithm' => 'alpha',
            ],
            'no_useless_else'                                   => true,
            'native_function_invocation'                        => ['include' => ['@compiler_optimized']],
            'nullable_type_declaration_for_default_null_value'  => true,
            'no_unneeded_control_parentheses'                   => true,
            'combine_consecutive_issets'                        => true,
            'combine_consecutive_unsets'                        => true,
            'no_useless_sprintf'                                => true,
            'indentation_type'                                  => true,
        ]
    )
    ->setFinder($finder);

return $config;
