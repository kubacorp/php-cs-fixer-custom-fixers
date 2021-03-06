<?php

/*
 * This file is part of PHP CS Fixer: custom fixers.
 *
 * (c) 2018 Kuba Werłos
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpCsFixerCustomFixers\Fixer;

use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Analyzer\NamespacesAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

final class PhpdocSelfAccessorFixer extends AbstractFixer
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'In PHPDoc inside class or interface element `self` should be preferred over the class name itself.',
            [new CodeSample('<?php
class Foo {
    /**
     * @var Foo
     */
     private $instance;
}
')]
        );
    }

    /**
     * Must run before NoSuperfluousPhpdocTagsFixer.
     */
    public function getPriority(): int
    {
        return 7;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([\T_CLASS, \T_INTERFACE]) && $tokens->isTokenKindFound(\T_DOC_COMMENT);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        $namespaces = (new NamespacesAnalyzer())->getDeclarations($tokens);

        foreach ($namespaces as $namespace) {
            $this->fixPhpdocSelfAccessor($tokens, $namespace->getScopeStartIndex(), $namespace->getScopeEndIndex(), $namespace->getFullName());
        }
    }

    private function fixPhpdocSelfAccessor(Tokens $tokens, int $namespaceStartIndex, int $namespaceEndIndex, string $fullName): void
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        $index = $namespaceStartIndex;
        while ($index < $namespaceEndIndex) {
            $index++;

            /** @var Token $token */
            $token = $tokens[$index];

            if (!$token->isGivenKind([\T_CLASS, \T_INTERFACE]) || $tokensAnalyzer->isAnonymousClass($index)) {
                continue;
            }

            /** @var int $nameIndex */
            $nameIndex = $tokens->getNextTokenOfKind($index, [[\T_STRING]]);

            /** @var Token $nameToken */
            $nameToken = $tokens[$nameIndex];

            /** @var int $startIndex */
            $startIndex = $tokens->getNextTokenOfKind($nameIndex, ['{']);

            $endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $startIndex);

            $classyName = $nameToken->getContent();

            $this->replaceNameOccurrences($tokens, $fullName, $classyName, $startIndex, $endIndex);

            $index = $endIndex;
        }
    }

    private function replaceNameOccurrences(Tokens $tokens, string $namespace, string $classyName, int $startIndex, int $endIndex): void
    {
        for ($index = $startIndex; $index < $endIndex; $index++) {
            /** @var Token $token */
            $token = $tokens[$index];

            if (!$token->isGivenKind(\T_DOC_COMMENT)) {
                continue;
            }

            $newContent = $this->getNewContent($token->getContent(), $namespace, $classyName);

            if ($newContent === $token->getContent()) {
                continue;
            }

            $tokens[$index] = new Token([\T_DOC_COMMENT, $newContent]);
        }
    }

    private function getNewContent(string $content, string $namespace, string $classyName): string
    {
        $docBlock = new DocBlock($content);

        $fqcn = ($namespace !== '' ? '\\' . $namespace : '') . '\\' . $classyName;

        foreach ($docBlock->getAnnotations() as $annotation) {
            if (!$annotation->supportTypes()) {
                continue;
            }

            $types = [];
            foreach ($annotation->getTypes() as $type) {
                /** @var string $type */
                $type = Preg::replace(
                    \sprintf('/(?<![a-zA-Z0-9_\x7f-\xff\\\\])(%s|%s)\b(?!\\\\)/', $classyName, \preg_quote($fqcn, '/')),
                    'self',
                    $type
                );

                $types[] = $type;
            }

            $annotation->setTypes($types);
        }

        return $docBlock->getContent();
    }
}
