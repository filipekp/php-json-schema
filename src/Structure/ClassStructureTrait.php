<?php

namespace Swaggest\JsonSchema\Structure;

use Swaggest\JsonSchema\Constraint\Properties;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\Wrapper;
use Swaggest\JsonSchema\NameMirror;

trait ClassStructureTrait
{
    use ObjectItemTrait;

    /**
     * @return Wrapper
     */
    public static function schema()
    {
        static $schemas = array();
        $className = get_called_class();
        $schemaWrapper = &$schemas[$className];

        if (null === $schemaWrapper) {
            $schema = new Schema();
            $properties = new Properties();
            $schema->properties = $properties;
            $schema->objectItemClass = $className;
            $schemaWrapper = new Wrapper($schema);
            static::setUpProperties($properties, $schema);
            if (null === $schema->getFromRefs()) {
                $schema->setFromRef('#/definitions/' . $className);
            }
            if ($properties->isEmpty()) {
                $schema->properties = null;
            }
            $properties->lock();
        }

        return $schemaWrapper;
    }

    /**
     * @return Properties|static|null
     */
    public static function properties()
    {
        return static::schema()->getProperties();
    }

    /**
     * @param mixed $data
     * @param Context $options
     * @return static|mixed
     * @throws \Swaggest\JsonSchema\Exception
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public static function import($data, Context $options = null)
    {
        return static::schema()->in($data, $options);
    }

    /**
     * @param mixed $data
     * @param Context $options
     * @return mixed
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function export($data, Context $options = null)
    {
        return static::schema()->out($data, $options);
    }

    /**
     * @param ObjectItemContract $objectItem
     * @return static
     */
    public static function pick(ObjectItemContract $objectItem)
    {
        $className = get_called_class();
        return $objectItem->getNestedObject($className);
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static;
    }

    protected $__validateOnSet = true; // todo skip validation during import

    public function jsonSerialize()
    {
        $result = new \stdClass();
        $schema = static::schema();
        $properties = $schema->getProperties();
        if (null !== $properties) {
            foreach ($properties->getDataKeyMap() as $propertyName => $dataName) {
                $value = $this->$propertyName;
                if ((null !== $value) || array_key_exists($propertyName, $this->__arrayOfData)) {
                    $result->$dataName = $value;
                }
            }
        }
        foreach ($schema->getNestedPropertyNames() as $name) {
            /** @var ObjectItem $nested */
            $nested = $this->$name;
            if (null !== $nested) {
                foreach ((array)$nested->jsonSerialize() as $key => $value) {
                    $result->$key = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @return static|NameMirror
     */
    public static function names()
    {
        static $nameflector = null;
        if (null === $nameflector) {
            $nameflector = new NameMirror();
        }
        return $nameflector;
    }

    public function __set($name, $column) // todo nested schemas
    {
        if ($this->__validateOnSet) {
            if ($property = static::schema()->getProperty($name)) {
                $property->out($column);
            }
        }
        $this->__arrayOfData[$name] = $column;
        return $this;
    }

    public static function className()
    {
        return get_called_class();
    }

    /**
     * @throws \Exception
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public function validate()
    {
        static::schema()->out($this);
    }
}