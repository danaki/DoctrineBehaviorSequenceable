<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Util\SequenceableHelper;

/**
 * Class SequenceableFilter
 *
 * @package SequenceableBundle
 */
class SequenceableFilter extends SQLFilter
{

	/**
	 * Gets the SQL query part to add to a query.
	 *
	 * @param ClassMetaData $targetEntity
	 * @param string        $targetTableAlias
	 *
	 * @return string The constraint SQL if there is available, empty string otherwise.
	 */
	public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
	{
		if( !SequenceableHelper::isSequenceable($targetEntity) )
		{
			return '';
		}

		return sprintf('%s.sequence = 0', $targetTableAlias);
	}
}