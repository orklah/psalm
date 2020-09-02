<?php
namespace Psalm\Tests\Internal\Provider;

/**
 * Used to determine which files reference other files, necessary for using the --diff
 * option from the command line.
 */
class FakeFileReferenceCacheProvider extends \Psalm\Internal\Provider\FileReferenceCacheProvider
{
    /** @var ?array */
    private $cached_file_references;

    /** @var ?array */
    private $cached_classlike_files;

    /** @var ?array */
    private $cached_method_class_references;

    /** @var ?array */
    private $cached_nonmethod_class_references;

    /** @var ?array */
    private $cached_method_member_references;

    /** @var ?array */
    private $cached_file_member_references;

    /** @var ?array */
    private $cached_method_missing_member_references;

    /** @var ?array */
    private $cached_file_missing_member_references;

    /** @var ?array */
    private $cached_unknown_member_references;

    /** @var ?array */
    private $cached_method_param_uses;

    /** @var ?array */
    private $cached_issues;

    /** @var array<string, array<string, int>> */
    private $cached_correct_methods = [];

    /**
     * @var array<
     *      string,
     *      array{
     *          0: array<int, array{0: int, 1: non-empty-string}>,
     *          1: array<int, array{0: int, 1: non-empty-string}>,
     *          2: array<int, array{0: int, 1: non-empty-string, 2: int}>
     *      }
     *  >
     */
    private $cached_file_maps = [];

    public function __construct()
    {
        $this->config = \Psalm\Config::getInstance();
    }

    /**
     * @return ?array
     */
    public function getCachedFileReferences(): ?array
    {
        return $this->cached_file_references;
    }

    /**
     * @return ?array
     */
    public function getCachedClassLikeFiles(): ?array
    {
        return $this->cached_classlike_files;
    }

    /**
     * @return ?array
     */
    public function getCachedMethodClassReferences(): ?array
    {
        return $this->cached_method_class_references;
    }

    /**
     * @return ?array
     */
    public function getCachedNonMethodClassReferences(): ?array
    {
        return $this->cached_method_class_references;
    }

    /**
     * @return ?array
     */
    public function getCachedFileMemberReferences(): ?array
    {
        return $this->cached_file_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedMethodMemberReferences(): ?array
    {
        return $this->cached_method_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedFileMissingMemberReferences(): ?array
    {
        return $this->cached_file_missing_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedMixedMemberNameReferences(): ?array
    {
        return $this->cached_unknown_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedMethodMissingMemberReferences(): ?array
    {
        return $this->cached_method_missing_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedMethodParamUses(): ?array
    {
        return $this->cached_method_missing_member_references;
    }

    /**
     * @return ?array
     */
    public function getCachedIssues(): ?array
    {
        return $this->cached_issues;
    }

    /**
     * @return void
     */
    public function setCachedFileReferences(array $file_references)
    {
        $this->cached_file_references = $file_references;
    }

    /**
     * @return void
     */
    public function setCachedClassLikeFiles(array $file_references)
    {
        $this->cached_classlike_files = $file_references;
    }

    /**
     * @return void
     */
    public function setCachedMethodClassReferences(array $method_class_references)
    {
        $this->cached_method_class_references = $method_class_references;
    }

    /**
     * @return void
     */
    public function setCachedNonMethodClassReferences(array $file_class_references)
    {
        $this->cached_nonmethod_class_references = $file_class_references;
    }

    /**
     * @return void
     */
    public function setCachedMethodMemberReferences(array $member_references)
    {
        $this->cached_method_member_references = $member_references;
    }

    /**
     * @return void
     */
    public function setCachedMethodMissingMemberReferences(array $member_references)
    {
        $this->cached_method_missing_member_references = $member_references;
    }

    /**
     * @return void
     */
    public function setCachedFileMemberReferences(array $member_references)
    {
        $this->cached_file_member_references = $member_references;
    }

    /**
     * @return void
     */
    public function setCachedFileMissingMemberReferences(array $member_references)
    {
        $this->cached_file_missing_member_references = $member_references;
    }

    /**
     * @return void
     */
    public function setCachedMixedMemberNameReferences(array $references)
    {
        $this->cached_unknown_member_references = $references;
    }

    /**
     * @return void
     */
    public function setCachedMethodParamUses(array $uses)
    {
        $this->cached_method_param_uses = $uses;
    }

    /**
     * @return void
     */
    public function setCachedIssues(array $issues)
    {
        $this->cached_issues = $issues;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getAnalyzedMethodCache()
    {
        return $this->cached_correct_methods;
    }

    /**
     * @param array<string, array<string, int>> $analyzed_methods
     *
     * @return void
     */
    public function setAnalyzedMethodCache(array $analyzed_methods)
    {
        $this->cached_correct_methods = $analyzed_methods;
    }

    /**
     * @return array<
     *      string,
     *      array{
     *          0: array<int, array{0: int, 1: non-empty-string}>,
     *          1: array<int, array{0: int, 1: non-empty-string}>,
     *          2: array<int, array{0: int, 1: non-empty-string, 2: int}>
     *      }
     *  >
     */
    public function getFileMapCache()
    {
        return $this->cached_file_maps;
    }

    /**
     * @param array<
     *      string,
     *      array{
     *          0: array<int, array{0: int, 1: non-empty-string}>,
     *          1: array<int, array{0: int, 1: non-empty-string}>,
     *          2: array<int, array{0: int, 1: non-empty-string, 2: int}>
     *      }
     *  > $file_maps
     *
     * @return void
     */
    public function setFileMapCache(array $file_maps)
    {
        $this->cached_file_maps = $file_maps;
    }

    /**
     * @param array<string, array{int, int}> $mixed_counts
     *
     * @return void
     */
    public function setTypeCoverage(array $mixed_counts)
    {
    }
}
