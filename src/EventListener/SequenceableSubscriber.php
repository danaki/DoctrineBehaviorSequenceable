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

		// iterate over all entities (to update)
		foreach( $unit_of_work->getScheduledEntityUpdates() as $_entity )
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
			$this->_backupEntity($args->getEntityManager(), $_entity);
		}


	}

	/**
	 * Creates a backup of the whole row with a new sequence number.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $entity_to_update
	 */
	protected function _backupEntity(EntityManager $entity_manager, $entity_to_update)
	{
		$unit_of_work = $entity_manager->getUnitOfWork();

		// generate backup entity
		$backup_entity = $this->_createBackupEntity($entity_manager, $entity_to_update);

		// and schedule entity for insertion
		$unit_of_work->scheduleForInsert($backup_entity);
		$unit_of_work->computeChangeSets(); // recompute changeset, see {@link http://stackoverflow.com/questions/9583058/inserting-element-in-doctrine-listener}
	}


	/**
	 * Creates a backup of the given entity with the origin entity data, a new sequence value and a remove id.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $entity_to_update
	 *
	 * @return Sequenceable
	 */
	protected function _createBackupEntity(EntityManager $entity_manager, $entity_to_update)
	{
		$class_name = get_class($entity_to_update);

		$meta_class = $this->_classMetadataContainer[ $class_name ]->getClassMetadata();

		$change_set = $entity_manager->getUnitOfWork()->getEntityChangeSet($entity_to_update);

		// clone entity
		$backup_entity = clone $entity_to_update;

		// set a new sequence number
		$backup_entity->setSequence(
			$this->_classMetadataContainer[ $class_name ]->getBackupSequenceNo($entity_manager, $entity_to_update)
		);

		// reset origin values
		foreach( $change_set as $_field_name => $_value )
		{
			$meta_class->setFieldValue($backup_entity, $_field_name, $_value[ 0 ]);
		}

		// remove id
		// attention: generatorType must be {@see ClassMetadata::GENERATOR_TYPE_IDENTITY} if removing the id(s) 
		foreach( $meta_class->getIdentifierColumnNames() as $_identifier_column_names )
		{
			$meta_class->setFieldValue($backup_entity, $_identifier_column_names, null);
		}

		return $backup_entity;
	}

}