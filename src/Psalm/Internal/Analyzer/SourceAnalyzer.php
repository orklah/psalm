<?php
namespace Psalm\Internal\Analyzer;

use Psalm\Aliases;
use Psalm\Codebase;
use Psalm\StatementsSource;
use Psalm\Type;

/**
 * @internal
 */
abstract class SourceAnalyzer implements StatementsSource
{
    /**
     * @var SourceAnalyzer
     */
    protected $source;

    public function __destruct()
    {
        $this->source = null;
    }

    /**
     * @return Aliases
     */
    public function getAliases(): Aliases
    {
        return $this->source->getAliases();
    }

    /**
     * @return array<string, string>
     */
    public function getAliasedClassesFlipped(): array
    {
        return $this->source->getAliasedClassesFlipped();
    }

    /**
     * @return array<string, string>
     */
    public function getAliasedClassesFlippedReplaceable(): array
    {
        return $this->source->getAliasedClassesFlippedReplaceable();
    }

    /**
     * @return string|null
     */
    public function getFQCLN(): ?string
    {
        return $this->source->getFQCLN();
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->source->getClassName();
    }

    /**
     * @return string|null
     */
    public function getParentFQCLN(): ?string
    {
        return $this->source->getParentFQCLN();
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->source->getFileName();
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->source->getFilePath();
    }

    /**
     * @return string
     */
    public function getRootFileName()
    {
        return $this->source->getRootFileName();
    }

    /**
     * @return string
     */
    public function getRootFilePath()
    {
        return $this->source->getRootFilePath();
    }

    /**
     * @param string $file_path
     * @param string $file_name
     *
     * @return void
     */
    public function setRootFilePath($file_path, $file_name)
    {
        $this->source->setRootFilePath($file_path, $file_name);
    }

    /**
     * @param string $file_path
     *
     * @return bool
     */
    public function hasParentFilePath($file_path): bool
    {
        return $this->source->hasParentFilePath($file_path);
    }

    /**
     * @param string $file_path
     *
     * @return bool
     */
    public function hasAlreadyRequiredFilePath($file_path): bool
    {
        return $this->source->hasAlreadyRequiredFilePath($file_path);
    }

    /**
     * @return int
     */
    public function getRequireNesting(): int
    {
        return $this->source->getRequireNesting();
    }

    /**
     * @psalm-mutation-free
     * @return StatementsSource
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get a list of suppressed issues
     *
     * @return array<string>
     */
    public function getSuppressedIssues(): array
    {
        return $this->source->getSuppressedIssues();
    }

    /**
     * @param array<int, string> $new_issues
     *
     * @return void
     */
    public function addSuppressedIssues(array $new_issues)
    {
        $this->source->addSuppressedIssues($new_issues);
    }

    /**
     * @param array<int, string> $new_issues
     *
     * @return void
     */
    public function removeSuppressedIssues(array $new_issues)
    {
        $this->source->removeSuppressedIssues($new_issues);
    }

    /**
     * @return null|string
     */
    public function getNamespace(): ?string
    {
        return $this->source->getNamespace();
    }

    /**
     * @return bool
     */
    public function isStatic(): bool
    {
        return $this->source->isStatic();
    }

    /**
     * @psalm-mutation-free
     */
    public function getCodebase() : Codebase
    {
        return $this->source->getCodebase();
    }

    /**
     * @psalm-mutation-free
     */
    public function getProjectAnalyzer() : ProjectAnalyzer
    {
        return $this->source->getProjectAnalyzer();
    }

    /**
     * @psalm-mutation-free
     */
    public function getFileAnalyzer() : FileAnalyzer
    {
        return $this->source->getFileAnalyzer();
    }

    /**
     * @return array<string, array<string, array{Type\Union}>>|null
     */
    public function getTemplateTypeMap(): ?array
    {
        return $this->source->getTemplateTypeMap();
    }

    public function getNodeTypeProvider() : \Psalm\NodeTypeProvider
    {
        return $this->source->getNodeTypeProvider();
    }
}
