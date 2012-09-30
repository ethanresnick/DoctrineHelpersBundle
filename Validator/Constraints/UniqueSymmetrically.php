<?php
namespace ERD\DoctrineHelpersBundle\Validator\Constraints;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that no entity already exists in which the two field values provided are flipped.
 * 
 * E.g. if you have an entity like [Name = Edward, NickName=Ted], it will return 
 * false for a new entity with [Name=Ted, NickName=Edward].
 * 
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jan 8, 2012 Ethan Resnick Design
 * @Annotation
 */
class UniqueSymmetrically extends Constraint
{
    public $message = 'A duplicate {{ class }} already exists, but with the values for {{ field_1 }} and {{ field_2 }} reversed.';
    public $em = null;
    public $fields = array();
    
    /**
     * {@inheritDoc}
     */
    public function validatedBy() 
    { 
        return 'erd_doctrine_helpers.validator.unique_symmetrically';
    }
    
    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}