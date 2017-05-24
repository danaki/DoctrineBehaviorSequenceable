<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity\Sequenceable;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity\SequenceableEntityContainer;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Exception\MappingException;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Util\SequenceableHelper;

/**
 * Class SequenceableSubscriber
 *
 * @package SequenceableBundle
 */
class SequenceableSubscriber implements EventSubscriber
{

	/**
	 * @var SequenceableEntityContainer[]
	 */
	protected $_classMetadataContainer = array();

	/**
	 * {@inheritDoc}
	 */
	public function getSubscribedEvents()
	{
		return [
			Events::loadClassMetadata,
			Events::onFlush,
		];
	}


	/**
	 * Event `loadClassMetadata` loads all mandatory data for all entities.
	 *
	 * @param LoadClassMetadataEventArgs $args
	 */
	public function loadClassMetadata(LoadClassMetadataEventArgs $args)
	{
		/** @var ClassMetadata $meta_class */
		$meta_class = $args->getClassMetadata();

		/** @var Sequenceable $_entity */
		if( !SequenceableHelper::isSequenceable($meta_class) )
		{
			return;
		}

		$class_name = $meta_class->getReflectionClass()->getName();

		if( !array_key_exists($class_name, $this->_classMetadataContainer) )
		{
			$this->_classMetadataContainer[ $class_name ] = new SequenceableEntityContainer($meta_class, $args->getEntityManager());
		}
	}

	/**
	 * Event `onFlush` backups the entity in a new row.
	 *
	 * @param OnFlushEventArgs $args
	 *
	 * @throws MappingException
	 */
	public function onFlush(OnFlushEventArgs $args)
	{
		$entity_manager = $args->getEntityManager();
		$unit_of_work   = $entity_manager->getUnitOfWork();

//		// iterate over all entities (to update)
//		foreach( $unit_of_work->getScheduledCollectionUpdates() as $_collection )
//		{
//			/** @var PersistentCollection $_collection */
//			$class_name = $_collection->getTypeClass()->getName();
//
//			/** @var Sequenceable $_entity */
//			if( !SequenceableHelper::isSequenceable($entity_manager->getClassMetadata($class_name)) )
//			{
//				continue;
//			}
//
//			if( !array_key_exists($class_name, $this->_classMetadataContainer) )
//			{
//				throw MappingException::unknownSequenceableEntity($class_name);
//			}
//
//			foreach( $_collection as $_element )
//			{
//				$entity_manager->merge($_element);
//
//				$unit_of_work->recomputeSingleEntityChangeSet($this->_classMetadataContainer[ $class_name ]->getClassMetadata(), $_element);
//			}
//		}

		// Attention: Get all entities before looping over all entities
		// because in the loops entities are added to the schedules (updates and insertions)! 
		$entities_to_update = $unit_of_work->getScheduledEntityUpdates();
		$entities_to_insert = $unit_of_work->getScheduledEntityInsertions();

		// iterate over all entities
		foreach( $entities_to_update as $_entity )
		{
			$class_name = get_class($_entity);

			/** @var Sequenceable $_entity */
			if( !SequenceableHelper::isSequenceable($entity_manager->getClassMetadata($class_name)) )
			{
				continue;
			}

			if( !array_key_exists($class_name, $this->_classMetadataContainer) )
			{
				throw MappingException::unknownSequenceableEntity($class_name);
			}

			// create a backup of the whole row
			$this->_backupUpdateEntity($args->getEntityManager(), $_entity);
		}

		foreach( $entities_to_insert as $_entity )
		{

			$class_name = get_class($_entity);

			/** @var Sequenceable $_entity */
			if( !SequenceableHelper::isSequenceable($entity_manager->getClassMetadata($class_name)) )
			{
				continue;
			}

			if( !array_key_exists($class_name, $this->_classMetadataContainer) )
			{
				throw MappingException::unknownSequenceableEntity($class_name);
			}

			if( trait_exists('\Knp\DoctrineBehaviors\Model\SoftDeletable\SoftDeletable')
				&& in_array(\Knp\DoctrineBehaviors\Model\SoftDeletable\SoftDeletable::class, class_uses($_entity))
			)
			{
				$this->_backupDeletedEntity($entity_manager, $_entity);
			}
		}
	}

	/**
	 * Creates a new entity as backup of the given entity which should be updated.
	 *
	 * The new backup entity (with the previous origin entity data) will be inserted as new row (with a new id) and gets a new sequence value.
	 *
	 * The update entity gets all new values and stays at sequence `0`.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $entity_to_update
	 */
	protected function _backupUpdateEntity(EntityManager $entity_manager, $entity_to_update)
	{
		$class_name = get_class($entity_to_update);

		$meta_class = $this->_classMetadataContainer[ $class_name ]->getClassMetadata();

		// get change set to restore old values in backup entity
		$change_set = $entity_manager->getUnitOfWork()->getEntityChangeSet($entity_to_update);

		// create backup entity
		$backup_entity = clone $entity_to_update;

		// set a new sequence number of backup
		$backup_entity->setSequence(
			$this->_classMetadataContainer[ $class_name ]->getBackupSequenceNo($entity_manager, $entity_to_update)
		);

		// reset origin values of backup
		foreach( $change_set as $_field_name => $_value )
		{
			// old values are saved in $_value[ 0 ], new values in $_value[ 1 ]
			$meta_class->setFieldValue($backup_entity, $_field_name, $_value[ 0 ]);
		}

		// remove id of backup to insert object as new row
		// attention: generatorType must be {@see ClassMetadata::GENERATOR_TYPE_IDENTITY} if removing the id(s) 
		foreach( $meta_class->getIdentifierColumnNames() as $_identifier_column_names )
		{
			$meta_class->setFieldValue($backup_entity, $_identifier_column_names, null);
		}

		// and schedule entity for insertion
		$entity_manager->getUnitOfWork()->scheduleForInsert($backup_entity);

		// compute change set, see {@link http://stackoverflow.com/questions/9583058/inserting-element-in-doctrine-listener}
		$entity_manager->getUnitOfWork()->computeChangeSet($meta_class, $backup_entity);
	}

	/**
	 * Inserts a backup of the current soft-deleted entity. If no soft-deleted entity exists, no backup will be inserted :-)
	 *
	 * To remain the current id at the new entity, the backup replaces the $new_entity, whereas the new entity updates the values of the
	 * (existing) soft-deleted entity.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $new_entity
	 */
	protected function _backupDeletedEntity(EntityManager $entity_manager, $new_entity)
	{
		$class_name = get_class($new_entity);

		$meta_class = $this->_classMetadataContainer[ $class_name ]->getClassMetadata();

		// get current deleted entity
		$deleted_entity = $this->_classMetadataContainer[ $class_name ]->getDeletedEntity($entity_manager, $new_entity);

		// if no deleted entity exists, no backup is needed
		if( is_null($deleted_entity) )
		{
			return;
		}

		$unit_of_work = $entity_manager->getUnitOfWork();

		// 1. save backup as "new" entity to remain the id at the right new entity

		// get change set to store new values in "old" soft-deleteable entity
		$change_set = $entity_manager->getUnitOfWork()->getEntityChangeSet($new_entity);

		// overwrite new entity with the "old" values to save a backup as a new entity (with new id)
		foreach( $meta_class->getFieldNames() as $_field_name )
		{

			if( in_array($_field_name, $meta_class->getIdentifierColumnNames()) )
			{
				continue;
			}

			$meta_class->setFieldValue($new_entity, $_field_name, $meta_class->getFieldValue($deleted_entity, $_field_name));
		}

		// update sequence number
		$new_entity->setSequence(
			$this->_classMetadataContainer[ $class_name ]->getBackupSequenceNo($entity_manager, $deleted_entity)
		);

		// recompute changeset, see {@link http://stackoverflow.com/questions/9583058/inserting-element-in-doctrine-listener}
		$unit_of_work->recomputeSingleEntityChangeSet($meta_class, $new_entity);

		// 2. save new entity as update of existing soft-deleted entity

		// reset new values to soft-deleted entity
		foreach( $change_set as $_field_name => $_value )
		{
			$meta_class->setFieldValue($deleted_entity, $_field_name, $_value[ 1 ]);
		}

		// but the remove id
		// attention: generatorType must be {@see ClassMetadata::GENERATOR_TYPE_IDENTITY} if removing the id(s) 
		foreach( $meta_class->getIdentifierColumnNames() as $_identifier_column_names )
		{
			$meta_class->setFieldValue($deleted_entity, $_identifier_column_names, null);
		}

		// also update existing soft-deleted entity which becomes non soft-deleted
		$unit_of_work->scheduleForUpdate($deleted_entity);
		// recompute changeset, see {@link http://stackoverflow.com/questions/9583058/inserting-element-in-doctrine-listener}
		$unit_of_work->recomputeSingleEntityChangeSet($meta_class, $deleted_entity);
	}

}