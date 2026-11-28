<?php

declare(strict_types=1);

namespace Psalm\Internal\FileManipulation;

use PhpParser\Comment\Doc;
use Psalm\FileManipulation;

use function array_reverse;
use function strlen;
use function substr_replace;

/**
 * The LegacySyntax fixes of each file, which a manipulator that rewrites a whole docblock applies to it, as its edit
 * replaces theirs
 *
 * @internal
 */
final class LegacySyntaxFixes
{
    /** @var array<string, list<FileManipulation>> the fixes of each file, in the order of the file */
    private static array $fixes = [];

    /**
     * Replaces the fixes of the file, which its analysis decides
     *
     * @param list<FileManipulation> $fixes
     * @psalm-capabilities read-globals|write-globals
     */
    public static function set(string $file_path, array $fixes): void
    {
        self::$fixes[$file_path] = $fixes;
    }

    /**
     * @psalm-capabilities read-props|read-globals
     */
    public static function apply(Doc $docblock, string $file_path): Doc
    {
        $text = $docblock->getText();
        $docblock_start = $docblock->getStartFilePos();
        $docblock_length = strlen($text);

        // from the last, so that each fix leaves the offsets of the ones before it
        foreach (array_reverse(self::$fixes[$file_path] ?? []) as $fix) {
            if ($fix->start >= $docblock_start && $fix->end - $docblock_start <= $docblock_length) {
                $start = $fix->start - $docblock_start;
                $text = substr_replace($text, $fix->insertion_text, $start, $fix->end - $fix->start);
            }
        }

        if ($text === $docblock->getText()) {
            return $docblock;
        }

        return new Doc(
            $text,
            $docblock->getStartLine(),
            $docblock_start,
            $docblock->getStartTokenPos(),
            $docblock->getEndLine(),
            $docblock->getEndFilePos(),
            $docblock->getEndTokenPos(),
        );
    }
}
