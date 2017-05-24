<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Annotation\SequenceableID;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Exception\MappingException;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Filter\SequenceableFilter;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Util\Math;

/**
 * An object of class SequenceableClassMetadataContainer represents a container
 * - to hold the assigned {@see MetaClassdata} of an entity,
 * - to initialize the sequence column and add the mandatory unique constraint (if not added before),
 * - to calculate the next backup sequence number of the related entity.
 *
 * @todo    add cache to annotation reader. maybe via service configuration?
 * @todo    currently only annotation is possible, see {@link http://www.doctrine-project.org/2010/07/19/your-own-orm-doctrine2.html}
 *
 * @package SequenceableBundle
 */
class SequenceableEntityContainer
{
	/**
	 * @var AnnotationReader
	 *
	 */
	protected static $_AnnotationReader = null;

	/**
	 * the name of the filter in the config.xml of the symfony2 project
	 *
	 * @var string
	 */
	protected static $_FilterName = '';

	/**
	 * meta data of the related entity
	 *
	 * @var ClassMetadata
	 */
	protected $_classMetadata = null;

	/**
	 * the class name of the related entity
	 *
	 * @var string
	 */
	protected $_className = '';

	/**
	 * all fields which are annotated with @SequenceableID tag
	 *
	 * @var string[]
	 */
	protected $_sequenceableIdFields = array();

	/**
	 * temporary array to save enabled soft-deletable filters
	 *
	 * @var string[]
	 */
	protected $_disabledSoftDeleteableFilters = array();

	/**
	 * Searches for the enabled filter SequenceableFilter and saves the name of the filter for further usage.
	 *
	 * @param EntityManager $entity_manager
	 *
	 * @return string
	 *
	 * @throws MappingException
	 */
	protected static function _GetFilterName(EntityManager $entity_manager)
	{
		foreach( $entity_manager->getFilters()->getEnabledFilters() as $_filter_name => $_filter_object )
		{
			if( get_class($_filter_object) === SequenceableFilter::class )
			{
				return $_filter_name;
			}
		}

		throw MappingException::filterNotAddedOrNotEnabled(SequenceableFilter::class);
	}

	/**
	 * Returns an sql table alias depending on the submitted alias.
	 *
	 * @param integer $index
	 *
	 * @return string
	 */
	protected static function _GetAlias($index)
	{
		return sprintf('t%d', $index);
	}

	/**
	 * Returns the cached annotation reader.
	 *
	 * @return AnnotationReader
	 */
	protected static function _GetAnnotationReader()
	{
		if( !is_null(self::$_AnnotationReader) )
		{
			return self::$_AnnotationReader;
		}

		self::$_AnnotationReader = new AnnotationReader();

		return self::$_AnnotationReader;
	}

	/**
	 * Initializes a SequenceableClassMetadataContainer for the specified {@see ClassMetadata}.
	 *
	 * @param EntityManager $entity_manager
	 * @param ClassMetadata $class_meta
	 *
	 * @throws MappingException
	 */
	public function __construct(ClassMetadata $class_meta, EntityManager $entity_manager)
	{
		// get the custom filter name only once
		if( empty(self::$_FilterName) )
		{
			// retrieve filter name from config 
			self::$_FilterName = self::_GetFilterName($entity_manager);
		}

		// save properties
		$this->_classMetadata = $class_meta;
		$this->_className     = $this->_classMetadata->getReflectionClass()->getName();

		// check validator type (actually only AUTO_INCREMENT is allowed) 
		if( ClassMetadata::GENERATOR_TYPE_IDENTITY !== $this->_classMetadata->generatorType )
		{
			throw MappingException::tableGeneratorTypeIsNotAuto($this->_className, $this->_classMetadata->generatorType);
		}

		// maybe add a new field/column to save the sequence no
		$this->_initializeSequenceColumn();

		// get all fields which are annotated with @SequenceableID.
		$this->_initializeSequenceableIdFields();

		// add a unique constraint to the entity
		$this->_initializeIndexes($entity_manager);
	}

	/**
	 *
	 */
	protected function _initializeSequenceColumn()
	{
		if( !$this->_classMetadata->hasField('sequence') )
		{
			$this->_classMetadata->mapField([
				'fieldName' => 'sequence',
				'type'      => 'smallint',
				'options'   => [
					'unsigned' => true,
					'default'  => 0,
				],
			]);

		}
	}

	/**
	 * Returns all fields of the submitted meta class, which are annotated with @SequenceableID.
	 *
	 * The method uses a internal cache variable to avoid multiple parsing with class {@see AnnotationReader}.
	 *
	 * @throws MappingException
	 */
	protected function _initializeSequenceableIdFields()
	{
		// iterate over all fields
		foreach( $this->_classMetadata->getReflectionClass()->getProperties() as $_field )
		{

			$_property_annotation = self::_GetAnnotationReader()->getPropertyAnnotation($_field, SequenceableID::class);

			// add field to output if @SequenceableID was found
			if( !is_null($_property_annotation) )
			{
				array_push($this->_sequenceableIdFields, $_field->getName());
			}
		}

		// @SequenceableID is missing (at least one property must have the annotation) 
		if( empty($this->_sequenceableIdFields) )
		{
			throw MappingException::missingSequenceableID($this->_classMetadata->getReflectionClass()->getName());
		}
	}

	/**
	 * Adds indexes to the entity
	 * - to avoid same sequence number for identical entities and
	 * - to increase query execution by using the filter {@see \SequenceableBundle\Filter\SequenceableFilter}.
	 *
	 * Attention: Call after property {@see SequenceableClassMetadataContainer::_sequenceableIdFields} was initialized.
	 *
	 * Example: The tag &#64;SequenceableID was mentioned for columns _date_, _room_id_ and _name_.
	 * A single unique constraint will be created for all thee columns plus the _sequence_ column:
	 * - `UNIQ_XXX ('date', 'room_id', 'name', 'sequence')`
	 * Additionally six indexes (covered from the combination of the three columns) will be created to increase sql speed by using the filter:
	 * - `IDX_XX1 ('room_id', 'sequence')`
	 * - `IDX_XX1 ('name', 'sequence')`
	 * - `IDX_XX1 ('date', 'sequence')`
	 * - `IDX_XX1 ('room_id', 'name', 'sequence')`
	 * - `IDX_XX1 ('date', 'name', 'sequence')`
	 * - `IDX_XX1 ('date', 'room_id', 'sequence')`
	 *
	 * @param EntityManager $entity_manager
	 */
	protected function _initializeIndexes(EntityManager $entity_manager)
	{
		// get builder from class
		$meta_data_builder = new ClassMetadataBuilder($this->_classMetadata);

		// all columns which are included in the unique constraint
		$unique_columns = array();

		// add all fields of $this->_sequenceableIdFields
		foreach( $this->_sequenceableIdFields as $_field )
		{
			// mapping field
			if( $this->_classMetadata->hasAssociation($_field) )
			{

				$_mapping = $this->_classMetadata->getAssociationMapping($_field);

				foreach( $_mapping[ 'joinColumns' ] as $__joinColumns )
				{
					array_push($unique_columns, $__joinColumns[ 'name' ]);
				}
			}
			// scalar field
			else
			{
				array_push($unique_columns, $_field);
			}
		}

		$indexes = Math::UniqueCombination($unique_columns);

		// add indexes and constraint
		foreach( $indexes as $_columns )
		{
			array_unshift($_columns, 'sequence');

			if( count($_columns) === ( count($unique_columns) + 1 ) )
			{
				$meta_data_builder->addUniqueConstraint($_columns, $this->_generateIdentifierName($_columns, 'uniq'));
			}
			else
			{
				$meta_data_builder->addIndex($_columns, $this->_generateIdentifierName($_columns, 'idx'));
			}
		}

		// and reset meta data
		$this->_classMetadata = $meta_data_builder->getClassMetadata();
		$entity_manager->getMetadataFactory()->setMetadataFor($this->_className, $this->_classMetadata);
	}

	/**
	 * Returns the assigned {@see ClassMetadata}.
	 *
	 * @return ClassMetadata
	 *
	 * @author Falko Matthies <falko.ma@web.de>
	 */
	public function getClassMetadata(): ClassMetadata
	{
		return $this->_classMetadata;
	}

	/**
	 * Returns the assigned class name of the entity.
	 *
	 * @return string
	 *
	 * @author Falko Matthies <falko.ma@web.de>
	 */
	public function getClassName(): string
	{
		return $this->_className;
	}

	/**
	 * Disables the sequenceable filter to get all sequences of an entity.
	 *
	 * @param EntityManager $entity_manager
	 */
	protected function _disableSequenceableFilter(EntityManager $entity_manager)
	{
		$entity_manager->getFilters()->disable(self::$_FilterName);
	}

	/**
	 * Enables the sequenceable filter to get only the current entity (with sequence `0`).
	 *
	 * @param EntityManager $entity_manager
	 */
	protected function _enableSequenceableFilter(EntityManager $entity_manager)
	{
		$entity_manager->getFilters()->enable(self::$_FilterName);
	}

	/**
	 * Disables the soft-deleteable filter(s) to get even the (sequences of) deleted entities.
	 *
	 * @param EntityManager $entity_manager
	 */
	protected function _disableSoftDeleteableFilters(EntityManager $entity_manager)
	{
		$filter_names = array_keys($entity_manager->getFilters()->getEnabledFilters());

		$softdeleteable_filters = array_filter($filter_names, function ($_filter_name)
		{
			return ( preg_match('/soft/i', $_filter_name) === 1 ) && ( preg_match('/delet/i', $_filter_name) === 1 );
		});

		foreach( $softdeleteable_filters as $_softdeleteable_filter )
		{
			$entity_manager->getFilters()->disable($_softdeleteable_filter);
			array_push($this->_disabledSoftDeleteableFilters, $_softdeleteable_filter);
		}
	}

	/**
	 * Enables the soft-deleteable filter(s) to get only (sequences of) non deleted entities.
	 *
	 * @param EntityManager $entity_manager
	 */
	protected function _enableSoftDeleteableFilters(EntityManager $entity_manager)
	{
		while( !empty($this->_disabledSoftDeleteableFilters) )
		{
			$_softdeleteable_filter = array_pop($this->_disabledSoftDeleteableFilters);
			$entity_manager->getFilters()->enable($_softdeleteable_filter);
		}
	}

	/**
	 * Returns the next empty sequence number for a new backup row of the specified entity.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $entity_to_update
	 *
	 * @return integer
	 */
	public function getBackupSequenceNo(EntityManager $entity_manager, $entity_to_update): int
	{
		$query_builder = $entity_manager->createQueryBuilder()
			->from($this->_className, self::_GetAlias(0))
			->select("MAX(t0.sequence) + 1 AS max_sequence");

		$alias_index = 0;

		$field = null;

		foreach( $this->_sequenceableIdFields as $_field )
		{
			// mapping field
			if( $this->_classMetadata->hasAssociation($_field) )
			{
				$alias_index++;

				$mapping = $this->_classMetadata->getAssociationMapping($_field);

				$query_builder->innerJoin($mapping[ 'targetEntity' ], self::_GetAlias($alias_index), Join::WITH, $query_builder->expr()->eq(
					self::_GetAlias($alias_index),
					sprintf('%s.%s', self::_GetAlias(0), $_field))
				);
			}
			// scalar field
			else
			{
				$_field_value = Type::getType($this->_classMetadata->getTypeOfField($_field))->convertToDatabaseValue(
					$this->_classMetadata->getFieldValue($entity_to_update, $_field),
					$entity_manager->getConnection()->getDatabasePlatform()
				);

				$query_builder
					->andWhere(sprintf('%s.%s = :%2$s', self::_GetAlias(0), $_field))
					->setParameter($_field, $_field_value);

			}
		}

		$this->_disableSoftDeleteableFilters($entity_manager);
		$this->_disableSequenceableFilter($entity_manager);

//		dump($query_builder->getDQL());
//		dump($query_builder->getQuery()->getSQL());
//		dump($query_builder->getQuery()->getParameters());
//		die();

		$next_sequence_no = (int) $query_builder->getQuery()->getSingleScalarResult();

		$this->_enableSequenceableFilter($entity_manager);
		$this->_enableSoftDeleteableFilters($entity_manager);

		return $next_sequence_no;
	}

	/**
	 * Returns the (current/sequence=`0`) soft-deleted entity for a entity which should be inserted as a new entity
	 * or `null` if no soft-deleted entity exists.
	 *
	 * @param EntityManager $entity_manager
	 * @param Sequenceable  $entity_to_insert
	 *
	 * @return Sequenceable|null
	 */
	public function getDeletedEntity(EntityManager $entity_manager, $entity_to_insert)
	{
		$query_builder = $entity_manager->createQueryBuilder()
			->from($this->_className, self::_GetAlias(0))
			->select(self::_GetAlias(0))
			->andWhere(sprintf('%s.sequence = 0', self::_GetAlias(0)));

		$alias_index = 0;

		$field = null;

		foreach( $this->_sequenceableIdFields as $_field )
		{
			// mapping field
			if( $this->_classMetadata->hasAssociation($_field) )
			{
				$alias_index++;

				$_referenced_entity = $this->_classMetadata->getFieldValue($entity_to_insert, $_field);

				$query_builder->andWhere(sprintf('%s.%s = ?%d',
					self::_GetAlias(0),
					$_field,
					$alias_index
				));

				$query_builder->setParameter($alias_index, $_referenced_entity);

			} // scalar field
			else
			{
				$_field_value = Type::getType($this->_classMetadata->getTypeOfField($_field))->convertToDatabaseValue(
					$this->_classMetadata->getFieldValue($entity_to_insert, $_field),
					$entity_manager->getConnection()->getDatabasePlatform()
				);

				$query_builder
					->andWhere(sprintf('%s.%s = :%2$s', self::_GetAlias(0), $_field))
					->setParameter($_field, $_field_value);
			}
		}

		$this->_disableSoftDeleteableFilters($entity_manager);

		try
		{
			$result = $query_builder->getQuery()->getSingleResult();
		}
		catch( NoResultException $exception )
		{
			$result = null;
		}
		finally
		{
			$this->_enableSoftDeleteableFilters($entity_manager);
		}

		return $result;
	}

	/**
	 * Clone of method {@see AbstractAsset::_generateIdentifierName()}.
	 *
	 * Generates an identifier from a list of column names obeying a certain string length.
	 *
	 * This is especially important for Oracle, since it does not allow identifiers larger than 30 chars,
	 * however building idents automatically for foreign keys, composite keys or such can easily create
	 * very long names.
	 *
	 * @param array   $columnNames
	 * @param string  $prefix
	 * @param integer $maxSize
	 *
	 * @return string
	 */
	protected function _generateIdentifierName($columnNames, $prefix = '', $maxSize = 30)
	{
		$hash = implode("", array_map(function ($column)
		{
			return dechex(crc32($column));
		}, $columnNames));

		return substr(strtoupper($prefix . "_" . $hash), 0, $maxSize);
	}

}
