<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\ControlStructures\DisallowYodaConditionsSniff;
use PhpCsFixer\Fixer\ArrayNotation\NoMultilineWhitespaceAroundDoubleArrowFixer;
use PhpCsFixer\Fixer\Comment\CommentToPhpdocFixer;
use PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationSpacesFixer;
use PhpCsFixer\Fixer\FunctionNotation\NullableTypeDeclarationForDefaultNullValueFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DeclareEqualNormalizeFixer;
use PhpCsFixer\Fixer\Operator\UnaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestClassRequiresCoversFixer;
use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use SlevomatCodingStandard\Sniffs\Classes\ClassConstantVisibilitySniff;
use SlevomatCodingStandard\Sniffs\Classes\ClassMemberSpacingSniff;
use SlevomatCodingStandard\Sniffs\Classes\DisallowMultiConstantDefinitionSniff;
use SlevomatCodingStandard\Sniffs\Classes\DisallowMultiPropertyDefinitionSniff;
use SlevomatCodingStandard\Sniffs\Classes\TraitUseDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Classes\UselessLateStaticBindingSniff;
use SlevomatCodingStandard\Sniffs\Commenting\UselessFunctionDocCommentSniff;
use SlevomatCodingStandard\Sniffs\Commenting\UselessInheritDocCommentSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\DisallowYodaComparisonSniff;
use SlevomatCodingStandard\Sniffs\Exceptions\ReferenceThrowableOnlySniff;
use SlevomatCodingStandard\Sniffs\Functions\RequireArrowFunctionSniff;
use SlevomatCodingStandard\Sniffs\Functions\RequireTrailingCommaInCallSniff;
use SlevomatCodingStandard\Sniffs\Functions\RequireTrailingCommaInDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Functions\StaticClosureSniff;
use SlevomatCodingStandard\Sniffs\Functions\StrictCallSniff;
use SlevomatCodingStandard\Sniffs\Functions\UnusedInheritedVariablePassedToClosureSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\ReferenceUsedNamesOnlySniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UselessAliasSniff;
use SlevomatCodingStandard\Sniffs\Numbers\RequireNumericLiteralSeparatorSniff;
use SlevomatCodingStandard\Sniffs\Operators\RequireCombinedAssignmentOperatorSniff;
use SlevomatCodingStandard\Sniffs\Operators\RequireOnlyStandaloneIncrementAndDecrementOperatorsSniff;
use SlevomatCodingStandard\Sniffs\Operators\SpreadOperatorSpacingSniff;
use SlevomatCodingStandard\Sniffs\PHP\DisallowDirectMagicInvokeCallSniff;
use SlevomatCodingStandard\Sniffs\PHP\ForbiddenClassesSniff;
use SlevomatCodingStandard\Sniffs\PHP\RequireNowdocSniff;
use SlevomatCodingStandard\Sniffs\PHP\UselessParenthesesSniff;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;
use SlevomatCodingStandard\Sniffs\PHP\UselessSemicolonSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;
use SlevomatCodingStandard\Sniffs\Variables\UnusedVariableSniff;
use SlevomatCodingStandard\Sniffs\Variables\UselessVariableSniff;
use SlevomatCodingStandard\Sniffs\Whitespaces\DuplicateSpacesSniff;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\CodingStandard\Fixer\Spacing\MethodChainingNewlineFixer;
use Symplify\CodingStandard\Fixer\Spacing\SpaceAfterCommaHereNowDocFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;



return function (ECSConfig $ecsConfig): void {
    $ecsConfig->indentation('spaces');
    $ecsConfig->cacheDirectory('var/ecs');
    $ecsConfig->cacheNamespace('JtcSolutions');

    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);
    $ecsConfig->skip([
        __DIR__ . '/src/DependencyInjection'
    ]);

    // this way you add a single rule
    $ecsConfig->rules([
        NoUnusedImportsFixer::class,
    ]);

    $skip = [];

    $ecsConfig->rule(MultilineWhitespaceBeforeSemicolonsFixer::class);

    $ecsConfig->ruleWithConfiguration(MultilineWhitespaceBeforeSemicolonsFixer::class, ['strategy' => MultilineWhitespaceBeforeSemicolonsFixer::STRATEGY_NO_MULTI_LINE]);

    $skip[PhpUnitInternalClassFixer::class] = null;
    $skip[PhpUnitTestClassRequiresCoversFixer::class] = null;
    // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/5908
    $skip[ReturnAssignmentFixer::class] = null;
    // https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/6002
    $skip[NoMultilineWhitespaceAroundDoubleArrowFixer::class] = null;

    $ecsConfig->ruleWithConfiguration(PhpUnitTestCaseStaticMethodCallsFixer::class, ['call_type' => PhpUnitTestCaseStaticMethodCallsFixer::CALL_TYPE_SELF]);
    $skip[CommentToPhpdocFixer::class] = null;

    $ecsConfig->import(SetList::CLEAN_CODE);

    $ecsConfig->import(SetList::DOCTRINE_ANNOTATIONS);
    $ecsConfig->ruleWithConfiguration(DoctrineAnnotationSpacesFixer::class, ['after_argument_assignments' => true, 'before_argument_assignments' => true]);

    $ecsConfig->import(SetList::COMMON);
    $skip[ArrayListItemNewlineFixer::class] = null;
    $skip[ArrayOpenerAndCloserNewlineFixer::class] = null;
    $skip[StandaloneLineInMultilineArrayFixer::class] = null;
    $skip[AssignmentInConditionSniff::class . '.FoundInWhileCondition'] = null;

    $ecsConfig->import(SetList::SYMPLIFY);
    $ecsConfig->ruleWithConfiguration(GeneralPhpdocAnnotationRemoveFixer::class, ['annotations' => ['author', 'package', 'covers']]);

    $skip[LineLengthFixer::class] = null;
    $skip[MethodChainingNewlineFixer::class] = null;
    $skip[SpaceAfterCommaHereNowDocFixer::class] = null;

    $ecsConfig->import(SetList::PSR_12);
    $skip[BlankLineAfterOpeningTagFixer::class] = null;
    $skip[DeclareEqualNormalizeFixer::class] = null;
    $skip[UnaryOperatorSpacesFixer::class] = null;

    $ecsConfig->rule(NullableTypeDeclarationForDefaultNullValueFixer::class);
    $ecsConfig->rule(DisallowDirectMagicInvokeCallSniff::class);
    $ecsConfig->rule(UnusedVariableSniff::class);
    $ecsConfig->rule(UselessVariableSniff::class);
    $ecsConfig->rule(UnusedInheritedVariablePassedToClosureSniff::class);
    $ecsConfig->rule(UselessSemicolonSniff::class);
    $ecsConfig->rule(UselessParenthesesSniff::class);
    $ecsConfig->rule(RequireCombinedAssignmentOperatorSniff::class);
    $ecsConfig->rule(DisallowMultiConstantDefinitionSniff::class);
    $ecsConfig->rule(DisallowMultiPropertyDefinitionSniff::class);
    $ecsConfig->rule(TraitUseDeclarationSniff::class);
    $ecsConfig->rule(UselessAliasSniff::class);
    $ecsConfig->rule(DuplicateSpacesSniff::class);
    $ecsConfig->rule(ReferenceThrowableOnlySniff::class);
    $ecsConfig->rule(DisallowYodaConditionsSniff::class);
    $ecsConfig->rule(ClassConstantVisibilitySniff::class);
    $ecsConfig->rule(UselessLateStaticBindingSniff::class);
    $ecsConfig->rule(UselessInheritDocCommentSniff::class);
    $ecsConfig->rule(DisallowYodaComparisonSniff::class);
    $ecsConfig->rule(StaticClosureSniff::class);
    $ecsConfig->rule(RequireOnlyStandaloneIncrementAndDecrementOperatorsSniff::class);
    $ecsConfig->rule(SpreadOperatorSpacingSniff::class);
    $ecsConfig->ruleWithConfiguration(DeclareStrictTypesSniff::class, ['declareOnFirstLine' => true]);
    $ecsConfig->ruleWithConfiguration(UnusedVariableSniff::class, ['ignoreUnusedValuesWhenOnlyKeysAreUsedInForeach' => true]);
    $ecsConfig->rule(RequireNowdocSniff::class);

    $ecsConfig->rule(ParameterTypeHintSniff::class);
    $skip[ParameterTypeHintSniff::class . '.MissingAnyTypeHint'] = null;
    $skip[ParameterTypeHintSniff::class . '.MissingTraversableTypeHintSpecification'] = null;
    $ecsConfig->rule(PropertyTypeHintSniff::class);
    $skip[PropertyTypeHintSniff::class . '.MissingAnyTypeHint'] = null;
    $skip[PropertyTypeHintSniff::class . '.MissingTraversableTypeHintSpecification'] = null;
    $ecsConfig->rule(ReturnTypeHintSniff::class);
    $skip[ReturnTypeHintSniff::class . '.MissingAnyTypeHint'] = null;
    $skip[ReturnTypeHintSniff::class . '.MissingTraversableTypeHintSpecification'] = null;

    $ecsConfig->rule(UselessFunctionDocCommentSniff::class);
    $ecsConfig->rule(RequireArrowFunctionSniff::class);
    $ecsConfig->ruleWithConfiguration(RequireNumericLiteralSeparatorSniff::class, ['minDigitsBeforeDecimalPoint' => 5, 'minDigitsAfterDecimalPoint' => 5]);
    $ecsConfig->rule(ClassMemberSpacingSniff::class);
    $ecsConfig->rule(StrictCallSniff::class);
    // AppendIterator has a nasty bug with Generators, it's better to not use it: https://bugs.php.net/bug.php?id=72692
    $ecsConfig->ruleWithConfiguration(ForbiddenClassesSniff::class, ['forbiddenClasses' => ['AppendIterator' => null]]);

    $ecsConfig->ruleWithConfiguration(ReferenceUsedNamesOnlySniff::class, ['allowFullyQualifiedGlobalFunctions' => true]);
    $ecsConfig->ruleWithConfiguration(DuplicateSpacesSniff::class, ['ignoreSpacesInAnnotation' => true]);

    $ecsConfig->rule(RequireTrailingCommaInDeclarationSniff::class);
    $ecsConfig->rule(RequireTrailingCommaInCallSniff::class);

    // this way you can add sets - group of rules
    $ecsConfig->sets([
        // run and fix, one by one
        SetList::SPACES,
        SetList::ARRAY,
        // SetList::DOCBLOCK,
        SetList::NAMESPACES,
        SetList::COMMENTS,
        SetList::PSR_12,
    ]);

    $ecsConfig->skip($skip);
};
