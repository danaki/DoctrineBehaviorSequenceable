<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Exception;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class MappingException
 *
 * @package SequenceableBundle
 */
class MappingException extends \Exception
{

	/**
	 * @param string  $class
	 * @param integer $generator_type
	 *
	 * @return MappingException
	 */
	public static function tableGeneratorTypeIsNotAuto(string $class, $generator_type)
	{
		return new self(sprintf(
			  "Specified entity \"%s\" must have generator type \"GENERATOR_TYPE_IDENTITY\" (int: %d), but type %d is given.\n"
			. "Maybe the entity has no auto_increment field.",
			$class,
			ClassMetadata::GENERATOR_TYPE_IDENTITY,
			$generator_type
		));
	}

	/**
	 * @param string string
	 *
	 * @return MappingException
	 */
	public static function missingSequenceableID(string $class)
	{
		return new self(sprintf(
			"No SequenceableID property specified for Entity \"%s\".\nEvery sequenceable entity must have at least one SequenceableID property.",
			$class
		));
	}

	/**
	 * @param string $filter_class
	 *
	 * @return MappingException
	 */
	public static function filterNotAddedOrNotEnabled(string $filter_class)
	{
		return new self(sprintf("Please add or enable the required filter \"%s\" in your config.", $filter_class));
	}

	/**
	 * @param string $class
	 *
	 * @return MappingException
	 */
	public static function unknownSequenceableEntity(string $class)
	{
		return new self(sprintf("The class \"%s\" was not loaded by SequenceableSubscriber::loadClassMetadata()", $class));
	}
}