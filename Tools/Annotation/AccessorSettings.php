<?php
namespace ERD\DoctrineHelpersBundle\Tools\Annotation;
use ERD\AnnotationHelpers\InheritableAnnotation;

/**
 * Represents an annotation that instructs the AccessorGenerator on the type of accessors to generate.
 * Marks an entity as indexable by the search bundle.
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 25, 2012 Ethan Resnick Design
 *
 * @Annotation
 * @property string $visibility The visibility of this property. Can be one of the visibility constants.
 * @property string|null $publicName The public name of the property, which will be used in the accessors.
 * If null, the property's name in the class will be used. This value allows you to expose an internal property
 * under a different method name, which is great for maintaining backwards compatibility or fulfilling an interface.
 * @property string|null $singular This property's *public name* as a singular. E.g. "page" for a property "pages".
 * @property string $otherSideMethodEnding For bidirectional many-to-many relationships, we can keep them in sync
 * automatically, but only if we know how to call the corresponding method (eg. $subject->addX() corresponds to
 * $x->addSubject()) on the other side. So this variable stores the ending (i.e. method name without add/remove) of
 * the other side method.
 */
class AccessorSettings extends InheritableAnnotation
{
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_READONLY = 'read-only';
    const VISIBILITY_WRITEONLY = 'write-only';

    public function __construct(array $data)
    {
        $this->addAllowedProperties(['visibility','publicName','singular','otherSideMethodEnding']);

        if(isset($data['value']) && !isset($data['visibility'])) { $this->visibility  = $data['value']; }
        if(isset($data['otherSideMethod'])) { $this->otherSideMethodEnding = $data['otherSideMethod']; }

        parent::__construct($data);
    }
}
