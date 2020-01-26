<?php
namespace Psalm\Type\Atomic;

class TLowercasedString extends TString
{
    /**
     * @return string
     */
    public function getKey()
    {
        return 'string';
    }

    public function getId()
    {
        return 'lowercase-string';
    }

    /**
     * @return bool
     */
    public function canBeFullyExpressedInPhp()
    {
        return false;
    }
}
