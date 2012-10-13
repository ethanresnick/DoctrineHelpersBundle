<?php
namespace ERD\DoctrineHelpersBundle\Provider;
use \Symfony\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Annotations\Reader;

/**
 * Provides all entities with a given class annotation, with the option to control whether that
 * annotation can be "inherited" from a parent class.
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jul 2, 2012 Ethan Resnick Design
 */
class AnnotatedEntitiesProvider implements DoctrineEntityProvider
{
    /** @var Registry */
    protected $doctrineRegistry;
    protected $reader;

    protected $annotationClass;
    protected $isInherited;

    /**
     * @param Registry $doctrineRegistry
     * 
     * @param string $annotationClass The FCQN of the class of the annotation
     * @param boolean $isInherited Whether finding the annotation on a parent class counts as the subclass having it.
     */
    public function __construct(Registry $doctrineRegistry, Reader $reader, $annotationClass, $isInherited)
    {
        $this->doctrineRegistry = $doctrineRegistry;
        $this->reader           = $reader;

        $this->annotationClass  = (string) $annotationClass;
        $this->isInherited      = (bool) $isInherited;
    }


    public function getAllEntities()
    {
        $finalEntities = array();
        $validClasses  = array(); //of className=>Metadata pairs at first, then a simple array of class names.

        $managers = $this->doctrineRegistry->getEntityManagers();
        
        foreach($managers as $em)
        {
            $classes = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
            
            foreach($classes as $class)
            {
                $metadata = $em->getClassMetadata($class);
                if($this->classHasAnnotation(new \ReflectionClass($class)))
                {
                    $validClasses[$class] = $metadata;
                }
            }

            //now we need to clean up the list of classes for doctrine, to make sure we don't
            //have both an entity class and its parent in the list, because that might slow down
            //the query (i.e. we have to ask for all the subclass's entities, and we would've 
            //gotten them anyway from querying the parent class).
            //
            //And the easiest way to do that is to check each element for subclasses and remove
            //those if they're found.
            /** 
             * @todo Offload this to Dctorine somehow, as it's really a query optimization we 
             * shouldn't have to worry about.
             */
            $classesToRemove = array();
            foreach($validClasses as $class=>$metadata)
            {
                //we can't query for mapped superclass's directly (since they aren't entities)
                //so we need to preserve their children. And in fact, we need to remove them from
                //the list because again, they aren't persisted so they have no entities associated
                //with them (but their children).
                if($metadata->isMappedSuperclass) { $classesToRemove[] = $class; continue; }

                foreach($metadata->subClasses as $subClass)
                {
                    if(array_key_exists($subClass, $validClasses))
                    {
                        $classesToRemove[] = $subClass;
                    }
                }
            }
            $validClasses = array_diff(array_keys($validClasses), $classesToRemove);

            //now finally load up the entities
            foreach($validClasses as $class)
            {
                $entities = $em->getRepository($class)->findAll();
                
                foreach($entities as $entity)
                {
                    $finalEntities[] = $entity;
                }
            }
        }

        return $finalEntities;        
    }
    
    protected function classHasAnnotation(\ReflectionClass $class)
    {
        $hasAnnotation = ($this->reader->getClassAnnotation($class, $this->annotationClass)!==null);
        
        if(!$this->isInherited)
        {
            return $hasAnnotation;
        }   
        else
        {
            //do a thorough search for the annotation
            while(($class = $class->getParentClass()) && !$hasAnnotation)
            {
                $hasAnnotation = ($this->reader->getClassAnnotation($class, $this->annotationClass)!==null);
            }       

            return $hasAnnotation;
        }
    }
}