## Sequenceable behavior extension for Doctrine2

**Sequenceable** behavior will backup entities on changing. It works through annotations and can backups
the entity on every update.

Features:

- Specific annotations for properties
- Compatible with other behaviours (KNP, Gedmo, etc.)

Restrictions:

- tested only with MySQL/MariaDB
- tested only with annotations


### Setup

#### Add bundle to app/AppKernel.php

``` php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        return [
            // [...]
            new SequenceableBundle\SequenceableBundle(),
        ];
    }
    
    // [...]
}
```


#### Add ORM Filter

    doctrine:
        orm:
            entity_managers:
                default:
                    filters:
                        sequenceable_filter:
                            class:   SequenceableBundle\Filter\SequenceableFilter
                            enabled: true

<a name="traits"></a>

### Usage

#### Use traits

You can use **Sequenceable** trait for quick when using annotation mapping.

At least one field marked as **`SequenceableID`** is mandatory. The value of this field is the
identifier for the sequence column to search for the amount of already existing backups.

``` php
<?php

use Doctrine\ORM\Mapping as ORM;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Entity\Sequenceable;
use Fincallorca\DoctrineBehaviors\SequenceableBundle\Annotation\SequenceableID;

/**
 * @ORM\Entity
 * @ORM\Table(name="my_entity")
 */
class MyEntity
{
    /**
     * Hook sequenceable behavior (adds field sequenceable).
     */
    use Sequenceable;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(length=128)
     * @SequenceableID
     */
    private $title;
}
```

##### Unique index

To ensure database integrity a unique index for the SequenceableID field(s) and the sequence field
will be automatically added.