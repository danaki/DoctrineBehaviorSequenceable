## Sequenceable behavior extension for Doctrine2

[![LICENSE](https://img.shields.io/badge/release-0.0.3-blue.svg?style=flat)](https://github.com/Fincallorca/DoctrineBehaviorSequenceable/releases/tag/0.0.3)
[![Packagist](https://img.shields.io/badge/Packagist-0.0.3-blue.svg?style=flat)](https://packagist.org/packages/fincallorca/doctrine-behaviors)
[![LICENSE](https://img.shields.io/badge/License-MIT-blue.svg?style=flat)](LICENSE)
[![https://jquery.com/](https://img.shields.io/badge/Symfony-≥3-red.svg?style=flat)](https://symfony.com/)
[![https://jquery.com/](https://img.shields.io/badge/Doctrine-≥2.2-red.svg?style=flat)](http://www.doctrine-project.org/)


In addition to the [DoctrineExtensions library](https://github.com/Atlantic18/DoctrineExtensions) or the [Doctrine Behaviors of KNP Labs](https://github.com/KnpLabs/DoctrineBehaviors)
this Bundle add a **sequence** behaviour to your entities.

The **Sequenceable** behavior backups entities on changing. Like other behaviours the bundle works through annotations.

**Features**:

- Specific annotations for properties
- Compatible with other behaviours (KNP, Gedmo, etc.)

**Restrictions**:

- tested only with MySQL/MariaDB
- tested only with annotations

### Table of Contents

* [Integration](#integration)
  * [Install via Composer](#install-via-composer)
  * [Add Bundle to Symfony Application](#add-bundle-to-symfony-application)
* [The Basics](#the-basics)
  * [Use traits](#use-traits)
  

### Integration

#### Install via Composer

```bash
composer require fincallorca/doctrine-behaviors "dev-master"
```

#### Add Bundle to Symfony Application

##### Add the `SequenceableBundle` to `app/AppKernel.php`

``` php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        return [
            // [...]
            new Fincallorca\DoctrineBehaviors\SequenceableBundle\SequenceableBundle(),
        ];
    }
    
    // [...]
}
```

##### Add ORM Filter to Configuration

Via the `config.yml`

```yaml
doctrine:
  orm:
    entity_managers:
      default:
        filters:
          sequenceable_filter:
            class:   Fincallorca\DoctrineBehaviors\SequenceableBundle\EventListener\SequenceableSubscriber
            enabled: true
```

### The Basics

#### Use traits

You can use **Sequenceable** trait for quick when using annotation mapping.

At least one field marked as **`SequenceableID`** is mandatory. The value of this field is the
identifier for the sequence column to search for the amount of already existing backups.

``` php
<?php

use Doctrine\ORM\Mapping as ORM;
use Fincallorca\DoctrineBehaviors\SequenceableBundle as SequenceableBehavior;

/**
 * @ORM\Entity
 * @ORM\Table(name="my_entity")
 */
class MyEntity
{
    /**
     * Hook sequenceable behavior (adds field sequenceable).
     */
    use SequenceableBehavior\Entity\Sequenceable;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(length=128)
     * @SequenceableBehavior\Annotation\SequenceableID
     */
    private $title;
}
```