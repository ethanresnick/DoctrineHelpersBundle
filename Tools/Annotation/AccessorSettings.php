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
     * @var string The visibility of this property
     */
    public $visibility = self::VISIBILITY_PUBLIC;
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
        if(isset($data['singular'])) { $this->singular = $data['singular']; }
        if(isset($data['otherSideMethod'])) { $this->otherSideMethodEnding = $data['otherSideMethod']; }
    }
}
