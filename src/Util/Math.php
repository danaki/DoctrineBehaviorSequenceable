<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\Util;

class Math
{
	/**
	 * @param mixed[] $in
	 * @param int     $minLength
	 * @param int     $max
	 *
	 * @return mixed[]
	 *
	 * @link http://stackoverflow.com/a/38871855/4351778
	 */
	public static function UniqueCombination($in, $minLength = 1, $max = 2000)
	{
		$count   = count($in);
		$members = pow(2, $count);
		$return  = array();
		for( $i = 0; $i < $members; $i++ )
		{
			$b   = sprintf("%0" . $count . "b", $i);
			$out = array();
			for( $j = 0; $j < $count; $j++ )
			{
				$b{$j} == '1' and $out[] = $in[ $j ];
			}

			count($out) >= $minLength && count($out) <= $max and $return[] = $out;
		}
		return $return;
	}

}