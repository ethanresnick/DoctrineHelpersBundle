<?php

namespace ERD\DoctrineHelpersBundle\Tools\Annotation;

/**
 * Represents an annotation that instructs the AccessorGenerator on the type of accessors to generate.
 * Marks an entity as indexable by the search bundle.
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 25, 2012 Ethan Resnick Design
 *
 * @Annotation
 */
class AccessorSettings
{
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_READONLY = 'read-only';
    const VISIBILITY_WRITEONLY = 'write-only';

    /**
     * @var string The visibility of this property. Can be one of the visibility constants.
     */
    public $visibility = self::VISIBILITY_PUBLIC;

    /**
     * @var string|null The public name of the property, which will be used in the accessors.
     * If null, the property's name in the class will be used. This value allows you to
     * expose an internal property under a different method name, which is great for maintaining
     * backwards compatibility or fulfilling an interface.
     */
    public $publicName = null;

    /**
     * @var string|null This property's public name as a singular. E.g. "page" for a property "pages".
     * Because the singular is only used in the names of add/remove methods for collections, whereas
     * inside the method the collection's plural name is used, it's always the singular of the *public*
     * name (if the private and public names differ).
     */
    public $singular = null;

    /**
     * @var string For bidirectional many-to-many relationships, we can keep them in sync
     * automatically, but only if we know how to call the corresponding method (eg.
     * $subject->addX() corresponds to $x->addSubject()) on the other side. So this variable
     * stores the ending (i.e. method name without add/remove) of the other side method.
     */
    public $otherSideMethodEnding = null;


    public function __construct(array $data)
    {
        if(isset($data['value'])) { $this->visibility  = $data['value']; }
        if(isset($data['otherSideMethod'])) { $this->otherSideMethodEnding = $data['otherSideMethod']; }

        foreach(['publicName','singular'] as $allowedKey)
        {
            if(isset($data[$allowedKey])) { $this->{$allowedKey} = $data[$allowedKey]; }
        }
    }
}
