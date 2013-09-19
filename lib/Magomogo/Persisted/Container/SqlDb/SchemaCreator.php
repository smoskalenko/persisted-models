<?php

namespace Magomogo\Persisted\Container\SqlDb;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Magomogo\Persisted\Container\ContainerInterface;
use Magomogo\Persisted\ModelInterface;
use Magomogo\Persisted\PossessionInterface;
use Magomogo\Persisted\Collection;
use Magomogo\Persisted\AbstractProperties;
use Magomogo\Persisted\Exception;

class SchemaCreator implements ContainerInterface
{
    /**
     * @var AbstractSchemaManager
     */
    private $manager;

    /**
     * @var NamesInterface
     */
    private $names;

    /**
     * @param $manager AbstractSchemaManager
     * @param $names NamesInterface
     */
    public function __construct($manager, $names)
    {
        $this->manager = $manager;
        $this->names = $names;
    }

    public function schemaFor(ModelInterface $model)
    {
        $model->save($this);
    }

    /**
     * @param \Magomogo\Persisted\AbstractProperties $propertyBag
     * @return \Magomogo\Persisted\AbstractProperties $propertyBag loaded with data
     */
    public function loadProperties($propertyBag)
    {
        trigger_error('Incorrect usage', E_USER_ERROR);
    }

    /**
     * @param \Magomogo\Persisted\AbstractProperties $propertyBag
     * @return \Magomogo\Persisted\AbstractProperties
     */
    public function saveProperties($propertyBag)
    {
        $tableName = $this->names->propertyBagToName($propertyBag);

        if (!in_array($tableName, $this->manager->listTableNames())) {
            $this->manager->createTable(
                $this->newTableObject($propertyBag, $tableName)
            );

            if ($propertyBag instanceof Collection\OwnerInterface) {
                /** @var Collection\AbstractCollection $collection */
                foreach ($propertyBag->collections() as $collectionName => $collection) {
                    $collection->putIn($this, $propertyBag);
                }
            }

        }

        $propertyBag->persisted($tableName, $this);
        return $propertyBag;
    }

    /**
     * @param array $propertyBags array of \Magomogo\Model\AbstractProperties
     * @return void
     */
    public function deleteProperties(array $propertyBags)
    {
        trigger_error('Incorrect usage', E_USER_ERROR);
    }

    /**
     * @param Collection\AbstractCollection $collectionBag
     * @param \Magomogo\Persisted\AbstractProperties $leftProperties
     * @param array $propertyBags array of \Magomogo\Model\AbstractProperties
     * @return void
     */
    public function referToMany($collectionBag, $leftProperties, array $propertyBags)
    {
        $referenceName = $this->names->manyToManyRelationName($collectionBag, $leftProperties);

        if (!empty($propertyBags) && !in_array($referenceName, $this->manager->listTableNames())) {
            $rightProperties = reset($propertyBags);
            $table = new Table($this->quoteIdentifier($referenceName));
            $this->addForeignReferenceColumn(
                $table, $this->names->propertyBagToName($leftProperties), $leftProperties
            );
            $this->addForeignReferenceColumn(
                $table, $this->names->propertyBagToName($rightProperties), $rightProperties
            );
            $this->manager->createTable($table);
        }
    }

    /**
     * @param string $collectionBag
     * @param \Magomogo\Persisted\AbstractProperties $leftProperties
     * @return array of \Magomogo\Model\AbstractProperties
     */
    public function listReferences($collectionBag, $leftProperties)
    {
        trigger_error('Incorrect usage', E_USER_ERROR);
    }

//----------------------------------------------------------------------------------------------------------------------

    private function quoteIdentifier($str)
    {
        return $this->manager->getDatabasePlatform()->quoteIdentifier($str);
    }

    /**
     * @param Table $table
     * @param string $fieldName
     * @param mixed $fieldValue
     * @return void
     * @throws \Magomogo\Persisted\Exception\Type
     */
    private function defineSchemaForField($table, $fieldName, $fieldValue) {
        if (is_string($fieldValue)) {
            $table->addColumn($this->quoteIdentifier($fieldName), 'text', array('notNull' => false));
        } elseif ($fieldValue instanceof ModelInterface) {
            $table->addColumn($this->quoteIdentifier($fieldName), 'integer', array('unsigned' => true, 'notNull' => false));
            $relatedTable = $fieldValue->save($this);
            $table->addForeignKeyConstraint(
                $this->quoteIdentifier($relatedTable),
                array($this->quoteIdentifier($fieldName)),
                array('id'),
                array('onUpdate' => 'RESTRICT', 'onDelete' => 'SET NULL')
            );
        } elseif ($fieldValue instanceof \DateTime) {
            $table->addColumn($this->quoteIdentifier($fieldName), 'datetimetz', array('notNull' => false));
        } else {
            throw new Exception\Type;
        }
    }

    /**
     * @param AbstractProperties $propertyBag
     * @param string $tableName
     * @return \Doctrine\DBAL\Schema\Table
     */
    private function newTableObject($propertyBag, $tableName)
    {
        $table = new Table($this->quoteIdentifier($tableName));

        if (!isset($propertyBag->id)) {
            $table->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
        }

        foreach ($propertyBag as $name => $value) {
            if (($name === 'id') && is_string($value)) {
                $table->addColumn('id', 'string', array('length' => 255, 'notnull' => true));
            } else {
                $this->defineSchemaForField($table, $name, $value);
            }
        }

        $table->setPrimaryKey(array('id'));

        if ($propertyBag instanceof PossessionInterface) {
            foreach ($propertyBag->foreign() as $propertyName => $foreignProperties) {
                $this->addForeignReferenceColumn($table, $propertyName, $foreignProperties);
            }
        }

        return $table;
    }

    /**
     * @param Table $table
     * @param string $columnName
     * @param AbstractProperties $leftProperties
     */
    private function addForeignReferenceColumn($table, $columnName, $leftProperties)
    {
        if ($leftProperties->naturalKey() && is_string($leftProperties->naturalKey())) {
            $table->addColumn($this->quoteIdentifier($columnName), 'string', array('length' => 255, 'notnull' => false));
        } else {
            $table->addColumn(
                $this->quoteIdentifier($columnName),
                'integer',
                array('unsigned' => true, 'notNull' => false)
            );
        }
        $table->addForeignKeyConstraint(
            $this->quoteIdentifier($this->names->propertyBagToName($leftProperties)),
            array($this->quoteIdentifier($columnName)),
            array('id'),
            array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE')
        );
    }

}