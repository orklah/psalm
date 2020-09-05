<?php
namespace Psalm\Internal\Type\ParseTree;

/**
 * @internal
 */
class CallableTree extends \Psalm\Internal\Type\ParseTree
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var bool
     */
    public $terminated = false;

    /**
     * @param \Psalm\Internal\Type\ParseTree|null $parent
     */
    public function __construct(string $value, \Psalm\Internal\Type\ParseTree $parent = null)
    {
        $this->value = $value;
        $this->parent = $parent;
    }
}
