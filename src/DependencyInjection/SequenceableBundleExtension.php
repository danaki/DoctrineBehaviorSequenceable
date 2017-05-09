<?php
namespace Fincallorca\DoctrineBehaviors\SequenceableBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

/**
 * Class SequenceableBundleExtension
 *
 * @package SequenceableBundleBundle\DependencyInjection
 *
 * @link    http://symfony.com/doc/current/bundles/extension.html
 *
 * @package SequenceableBundle
 */
class SequenceableBundleExtension extends Extension
{
	public function load(array $configs, ContainerBuilder $container)
	{
		$loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
		$loader->load('services.yml');
	}
}