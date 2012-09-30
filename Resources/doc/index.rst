Doctrine Helpers Bundle
=======================

This bundle includes a number of classes that make it easier to work with Doctrine. It is a 
required dependency for some ERD bundles (at least when they are configured to hook into Doctrine).

At present, this bundle includes a set of providers, which get a collection of Doctrine entities 
that would be hard to query for directly. Any object that needs those entities can then 
dependency-inject the provider.

The providers are located under the `ERD\DoctrineHelpersBundle\Provider` namespace. They include:

* `AllEntitiesProvider`: Returns all entities known to Doctrine across all its Event Managers.
* `AnnotatedEntitiesProvider`: Returns all entities known to Doctrine that have a given class 
   annotation or, optionally, that have a parent class (mapped or not) with that annotation.
* `InterfacedEntitiesProvider`: Returns all Doctrine entities that implement a given interface.

Finally, `DoctrineEntityProvider` is an interface that all the above providers implement.


The bundle also includes a couple validation constraints that work by looking at all the Doctrine
entities. These are in ERD\DoctrineHelpersBundle\Validator\Constraints`