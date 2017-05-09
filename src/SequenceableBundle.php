<?php

namespace Fincallorca\DoctrineBehaviors\SequenceableBundle;

use Fincallorca\DoctrineBehaviors\SequenceableBundle\DependencyInjection\SequenceableBundleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SequenceableBundle extends Bundle
{
	public function getContainerExtension()
	{
		return new SequenceableBundleExtension();
	}
}
