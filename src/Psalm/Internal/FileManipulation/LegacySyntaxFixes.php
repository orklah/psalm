<?php

declare(strict_types=1);

namespace Psalm\Internal\FileManipulation;

use PhpParser\Comment\Doc;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\IssueBuffer;

use function krsort;
use function strlen;
use function substr_replace;

/**
 * Applies the LegacySyntax fixes to a docblock a manipulator rewrites whole, as its edit replaces theirs
 *
 * @internal
 */
final class LegacySyntaxFixes
{
    public static function apply(Doc $docblock, string $file_path): Doc
    {
        $project_analyzer = ProjectAnalyzer::getInstance();

        if (!isset($project_analyzer->getIssuesToFix()['LegacySyntax'])) {
            return $docblock;
        }

        $text = $docblock->getText();
        $docblock_start = $docblock->getStartFilePos();
        $docblock_end = $docblock_start + strlen($text);
        $fixes = [];

        $file_storage = $project_analyzer->getCodebase()->file_storage_provider->get($file_path);

        foreach ($file_storage->legacy_syntax_issues as [$legacy_syntax, $suppressed_issues]) {
            $location = $legacy_syntax->code_location;

            if ($location->raw_file_start >= $docblock_start
                && $location->raw_file_end <= $docblock_end
                && !IssueBuffer::isSuppressedInList($legacy_syntax, $suppressed_issues)
            ) {
                $fixes[$location->raw_file_start - $docblock_start] = $legacy_syntax;
            }
        }

        if (!$fixes) {
            return $docblock;
        }

        // from the last, so that each fix leaves the offsets of the ones before it
        krsort($fixes);

        foreach ($fixes as $start => $legacy_syntax) {
            $length = $legacy_syntax->code_location->raw_file_end - $docblock_start - $start;
            $text = substr_replace($text, $legacy_syntax->replacement, $start, $length);
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
