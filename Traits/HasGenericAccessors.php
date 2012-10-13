<?php
namespace ERD\DoctrineHelpersBundle\Traits;

/**
 * Offers a method you can place in __call to automatically provide default
 * getters and setters for your entities. Any auto getters and setters will
 * seamlessly be overridden by those you define manually.
 *
 */
trait HasGenericAccessors
{
    protected function autoGetAndSet($methodName, $args)
    {
        if (preg_match('~^(set|get)([A-Z])(.*)$~', $methodName, $matches))
        {
            $prop = strtolower($matches[2]).$matches[3];
            if (property_exists($this, $prop) &&
                !(property_exists($this, '_inaccessibleProperties') && in_array($prop, $this->_inaccessibleProperties)))
            {
                if($matches[1]=='set' && count($args)==1)
                {
                    $this->{$prop} = $args[0];
                    return true;
                }
                else if($matches[1]=='get' && count($args)==0)
                {
                    return $this->{$prop};
                }
            }
        }
    }

    /**
     * __get and __set need to be here too, for symfony's form component
     * @see https://github.com/symfony/symfony/issues/4683
     */
    public function __get($name) {
        $this->autoGetAndSet('get'.ucfirst($name), array());
    }

    public function __set($name, $args)
    {
        $this->autoGetAndSet('set'.ucfirst($name), $args);
    }
}
