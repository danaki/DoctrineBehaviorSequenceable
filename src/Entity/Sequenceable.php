<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity;

/**
 * Sequenceable trait.
 *
 * Should be used inside entity, that needs to be sequenced.
 * 
 * @package SequenceableBundle
 */
trait Sequenceable
{
	/**
	 * @var integer
	 * @ORM\Column(type="smallint", options={"unsigned":true, "default":0})
	 */
	protected $sequence = 0;

	/**
	 * Returns the sequence of the entity.
	 *
	 * @return integer
	 *
	 * @author Falko Matthies <falko.matthies@fincallorca.de>
	 */
	public function getSequence()
	{
		return $this->sequence;
	}

	/**
	 * Sets the sequence of the entity.
	 *
	 * @param integer $sequence
	 *
	 * @return Sequenceable
	 *
	 * @author Falko Matthies <falko.matthies@fincallorca.de>
	 */
	public function setSequence($sequence)
	{
		$this->sequence = $sequence;
		return $this;
	}
}
