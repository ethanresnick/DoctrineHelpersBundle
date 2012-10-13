<?php
namespace ERD\DoctrineHelpersBundle\Traits;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Bidirectional (many-to-many) relationships in Doctrine are a bit challenging because both entities have
 * a collection of entities (e.g. users have a collection of groups while groups have a collection of users)
 * and these collections should be kept in sync (adding a user to a group adds the group to the user).
 * These methods help with that problem.
 *
 * NOTE: This class assumes that the collections on both sides of the relationship can only have one copy of
 * each item, i.e. it doesn't allow duplicates in either copy (and this is essential to how it handles the
 * problem of each side updating the other without creating an infinite loop). If you need to store multiple
 * copies of the same item in each collection, you'll have to find another approach.
 *
 */
trait HasBidirectionalManyToMany
{
    /**
     * @param $newItem object The item to add to the collection on both sides of the relation
     * @param $localCollection ArrayCollection The local collection of elements (e.g. $this->myAssociatedObjects)
     * @param $otherSideAdder string The name of the public adder method on the other side. Called to keep the collection in sync.
     *
     * @return bool True if the new item was added successfully; false if it was a duplicate (in which case it isn't re-added).
     */
    protected function addToBothCollections($newItem, &$localCollection, $otherSideAdder)
    {
        if(!in_array($newItem, $localCollection->toArray()))
        {
            $localCollection[] = $newItem;
            $newItem->{$otherSideAdder}($this);
            return true;
        }

        return false;
    }

    /**
     * @param $itemToRemove object The item to remove from the collection on both sides of the relation
     * @param $localCollection ArrayCollection The local collection of elements (e.g. $this->myAssociatedObjects)
     * @param $otherSideRemover string The name of the public remove method on the other side. Called to keep the collection in sync.
     *
     * @return bool True if the new item was removed successfully; false if it wasn't present (including if it was already removed).
     */
    protected function removeFromBothCollections($itemToRemove, &$localCollection, $otherSideRemover)
    {
        if(in_array($itemToRemove, $localCollection->toArray()))
        {
            $localCollection->removeElement($newItem);
            $localCollection = new ArrayCollection($localCollection->getValues()); //reindex
            $itemToRemove->{$otherSideRemover}($this);
            return true;
        }

        return false;
    }

    /**
     * @param $itemsToSet object
     * @param $localCollection ArrayCollection
     * @param $otherSideRemover string
     * @param $otherSideAdder string
     */
    public function setInBothCollections($itemsToSet, &$localCollection, $otherSideRemover, $otherSideAdder)
    {
        //in case the other side is the owning side, empty it there
        foreach($localCollection as &$localItemToRemove) { $localItemToRemove->{$otherSideRemover}(); }

        //then zero out the collection here.
        $localCollection = null;

        //then add to both sides
        foreach($itemsToSet as &$itemToSet) { $this->addToBothCollections($itemToSet, $localCollection, $otherSideAdder); }
    }
}
