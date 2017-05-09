<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Util;

use Doctrine\ORM\Mapping\ClassMetadata;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity\Sequenceable;

/**
 * Class SequenceableHelper
 *
 * @package SequenceableBundle
 */
class SequenceableHelper
{

	/**
	 * Checks if entity is sequenceable.
	 *
	 * @param ClassMetadata $meta
	 *
	 * @return boolean
	 *
	 */
	public static function isSequenceable(ClassMetadata $meta)
	{
		return in_array(Sequenceable::class, $meta->getReflectionClass()->getTraitNames());
	}

}