<?php
namespace ERD\DoctrineHelpersBundle\Provider;

/**
 * Description of AllEntitiesProvider
 *
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jun 25, 2012 Ethan Resnick Design
 */
class AllEntitiesProvider implements DoctrineEntityProvider
{
    protected $doctrineRegistry;
    
    public function __construct(\Symfony\Bundle\DoctrineBundle\Registry $doctrineRegistry)
    {
        $this->doctrineRegistry = $doctrineRegistry;
    }
    
    public function getAllEntities()
    {
        $finalEntities = array();

        $managers = $this->doctrineRegistry->getEntityManagers();
        
        foreach($managers as $em)
        {
            $classes = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();

            foreach($classes as $class)
            {
                $metadata = $em->getClassMetadata($class);
                
                //filter out mapped super classes and entities that have a parent repository (so we don't load them twice)
                if($metadata->isMappedSuperclass || ($metadata->rootEntityName!==$metadata->name)) { continue; }
                
                $entities = $em->getRepository($class)->findAll();
                
                foreach($entities as $entity)
                {
                    $finalEntities[] = $entity;
                }
            }
        }

        return $finalEntities;
    }
}