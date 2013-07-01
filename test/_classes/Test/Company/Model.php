<?php
namespace Test\Company;

use Magomogo\Persisted\Container\ContainerInterface;
use Magomogo\Persisted\ModelInterface;
use Magomogo\Persisted\OwnerInterface;
use Magomogo\Persisted\PossessionInterface;
use Test\JobRecord;
use Test\Person;

class Model implements ModelInterface, OwnerInterface
{
    /**
     * @var Properties
     */
    private $properties;

    /**
     * @param ContainerInterface $container
     * @param string $id
     * @return self
     */
    public static function load($container, $id)
    {
        $p = new Properties();
        $p->persisted($id, $container);
        return new self($p->loadFrom($container));
    }

    public function save($container)
    {
        return $this->properties->putIn($container);
    }

//----------------------------------------------------------------------------------------------------------------------

    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    public function name()
    {
        return $this->properties->name;
    }

    /**
     * @param PossessionInterface $properties
     * @param null|string $relationName
     * @return Properties
     */
    public function isOwner($properties, $relationName = null)
    {
        return $properties->isOwnedBy($this->properties, $relationName);
    }
}
