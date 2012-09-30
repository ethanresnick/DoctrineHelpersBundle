<?php
namespace ERD\DoctrineHelpersBundle\Validator\Constraints;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
/**
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jan 8, 2012 Ethan Resnick Design
 */
class UniqueSymmetricallyValidator extends ConstraintValidator
{
    protected $doctrine;
    
    public function __construct(RegistryInterface $registry)
    {
        $this->doctrine = $registry;
    }
    
    public function isValid($entity, Constraint $constraint)
    {   
        if (!is_array($constraint->fields) || count($constraint->fields) !== 2) 
        { 
            throw new ConstraintDefinitionException("The 'fields' option must an array with two fields."); 
        }

        $em = $this->doctrine->getEntityManager($constraint->em);
        $className = $this->context->getCurrentClass();
        $class = $em->getClassMetadata($className);
        $field1 = $constraint->fields[0];
        $field2 = $constraint->fields[1];   
        if (!isset($class->reflFields[$field1]) || !isset($class->reflFields[$field2]))
        {
            throw new ConstraintDefinitionException("Both fields must be mapped by Doctrine.");
        }
        
        $repository = $em->getRepository($className);
        $criteria = array($field1 => $class->reflFields[$field2]->getValue($entity), 
                          $field2 => $class->reflFields[$field1]->getValue($entity));
        $result = $repository->findBy($criteria);

        if(count($result) > 0 && $result[0] !== $entity)
        {
            $this->setMessage($constraint->message, array('{{ class }}'=>$className, '{{ field_1 }}'=>$field1, '{{ field_2 }}'=>$field2));
            return false;
        }  
        
        return true;
    }
}