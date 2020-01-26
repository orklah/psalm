<?php
namespace Psalm\Type\Atomic;

class TLowercasedString extends TString
{
    /**
     * @return string
     */
    public function getKey()
    {
        return 'lowercase-string';
    }

    public function getId()
    {
        return $this->getKey();
    }

    /**
     * @return bool
     */
    public function canBeFullyExpressedInPhp()
    {
        return false;
    }
}
