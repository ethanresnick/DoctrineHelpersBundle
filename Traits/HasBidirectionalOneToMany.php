<?php
namespace ERD\DoctrineHelpersBundle\Traits;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Bidirectional (one-to-many) relationships in Doctrine are a bit challenging because both entities have
 * an entity or collection of entities (e.g. a user has a role while a role has a collection of users) and
 * these should be kept in sync (changing the user's $role updates the $users collection of the affected groups).
 * These methods help with that problem.
 *
 * NOTE: This class assumes that the collections on the many side of the relationship can only have one copy of
 * each item, i.e. it doesn't allow duplicates (and this is essential to how it handles the  problem of each side
 * updating the other without creating an infinite loop). If you need to store multiple copies of the same item
 * in the collection, you'll have to find another approach.
 *
 */
trait HasBidirectionalOneToMany
{
    /**
     * @todo Write based on the HasBidirectionalManyToMany trait and integrate with the AccessorsGenerator.
     */
}
