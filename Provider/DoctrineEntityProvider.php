<?php
namespace ERD\DoctrineHelpersBundle\Provider;

/**
 * Designates a class that returns a collection of Doctrine entities.
 *
 * Providers are meant to offer a convenient way to inject a set of entities that wouldn't be 
 * easy to query for directly (otherwise we could just inject a query object or something without
 * having to build our own "provider" concept).
 * 
 * @author Ethan Resnick Design <hi@ethanresnick.com>
 * @copyright Jul 2, 2012 Ethan Resnick Design
 */
interface DoctrineEntityProvider
{
    public function getAllEntities();
}