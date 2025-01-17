<?php

namespace Vangrg\ProfanityBundle\Service;

use Vangrg\ProfanityBundle\Storage\ProfanitiesStorageInterface;

/**
 * Class ProfanityChecker.
 */
class ProfanityChecker
{
    private const SEPARATOR_PLACEHOLDER = '{!!}';

    private ProfanitiesStorageInterface $storage;
    private string $separatorExpression;
    private array $characterExpressions;
    private bool $allowBoundByWords;

    private string $currentExpression = '';
    private string $currentProfanity = '';
    private array $regularExpressions = [];
    private array $profanities = [];

    protected array $escapedSeparatorCharacters = ['\s'];
    protected array $separatorCharacters = [
        '@', '#', '%', '&', '_', ';', "'", '"', ',', '~', '`', '|', '!', '$', '^',
        '*', '(', ')', '-', '+', '=', '{', '}', '[', ']', ':', '<', '>', '?', '.', '/'
    ];

    protected array $characterSubstitutions = [
        '/a/' => ['a', '4', '@', 'Á', 'á', 'À', 'Â', 'à', 'Ä', 'ä', 'Å', 'å', 'æ', 'Æ'],
        '/b/' => ['b', '8', 'ß', 'Β', 'β'],
        '/c/' => ['c', 'Ç', 'ç', '¢', '<', '('],
        // Add remaining characters here...
    ];

    public function __construct(ProfanitiesStorageInterface $storage, bool $allowBoundByWords)
    {
        $this->storage = $storage;
        $this->allowBoundByWords = $allowBoundByWords;

        $this->separatorExpression = $this->generateSeparatorExpression();
        $this->characterExpressions = $this->generateCharacterExpressions();
    }

    public function hasProfanity(string $string): bool
    {
        if ($string === '') {
            return false;
        }

        $this->currentExpression = '';
        $this->currentProfanity = '';

        $expressions = $this->generateRegularExpressions();

        foreach ($expressions as $key => $expression) {
            if ($this->stringHasProfanity($string, $expression)) {
                $this->currentExpression = $expression;
                $this->currentProfanity = $this->profanities[$key] ?? '';
                return true;
            }
        }

        return false;
    }

    public function obfuscateIfProfane(string $string): string
    {
        while ($this->hasProfanity($string)) {
            $string = preg_replace(
                $this->currentExpression,
                str_repeat('*', strlen($this->currentProfanity)),
                $string
            );
        }

        return $string;
    }

    private function generateRegularExpressions(): array
    {
        if (!$this->storage->checkIfDataHasChanged() && !empty($this->regularExpressions)) {
            return $this->regularExpressions;
        }

        $this->profanities = $this->storage->getProfanities();
        $this->regularExpressions = array_map(
            fn($profanity) => $this->generateProfanityExpression($profanity),
            $this->profanities
        );

        return $this->regularExpressions;
    }

    private function stringHasProfanity(string $string, string $expression): bool
    {
        return preg_match($expression, $string) === 1;
    }

    private function generateProfanityExpression(string $word): string
    {
        $start = '/(^|' . $this->separatorExpression . ')' . $this->getOptionalWordsBounding();
        $end = '($|' . $this->separatorExpression . ')' . $this->getOptionalWordsBounding();

        $pattern = preg_replace(
            array_keys($this->characterExpressions),
            array_values($this->characterExpressions),
            $word
        );

        return str_replace(
            self::SEPARATOR_PLACEHOLDER,
            $this->separatorExpression . '*',
            $start . $pattern . $end . '/i'
        );
    }

    private function getOptionalWordsBounding(): string
    {
        return $this->allowBoundByWords ? '?' : '';
    }

    private function generateSeparatorExpression(): string
    {
        return $this->generateEscapedExpression($this->separatorCharacters, $this->escapedSeparatorCharacters);
    }

    private function generateCharacterExpressions(): array
    {
        return array_map(
            fn($subs) => $this->generateEscapedExpression($subs) . self::SEPARATOR_PLACEHOLDER,
            $this->characterSubstitutions
        );
    }

    private function generateEscapedExpression(array $chars, array $escapedChars = []): string
    {
        $allChars = array_merge($escapedChars, array_map(static fn($c) => preg_quote($c, '/'), $chars));
        return '[' . implode('', $allChars) . ']';
    }

    public function clearSeparatorExpression(): self
    {
        $this->separatorExpression = $this->generateEscapedExpression([], ['\s']);
        return $this;
    }
}
