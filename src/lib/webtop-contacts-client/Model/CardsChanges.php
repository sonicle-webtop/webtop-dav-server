<?php
/**
 * CardsChanges
 *
 * PHP version 5
 *
 * @category Class
 * @package  WT\Client\Contacts
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * WebTop Contacts
 *
 * No description provided (generated by Swagger Codegen https://github.com/swagger-api/swagger-codegen)
 *
 * OpenAPI spec version: v1
 * Contact: dev-team@sonicle.com
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 2.4.0-SNAPSHOT
 */

/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace WT\Client\Contacts\Model;

use \ArrayAccess;
use \WT\Client\Contacts\ObjectSerializer;

/**
 * CardsChanges Class Doc Comment
 *
 * @category Class
 * @description Bean for carry card collection changes
 * @package  WT\Client\Contacts
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class CardsChanges implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'CardsChanges';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'syncToken' => 'string',
        'inserted' => '\WT\Client\Contacts\Model\CardChanged[]',
        'updated' => '\WT\Client\Contacts\Model\CardChanged[]',
        'deleted' => '\WT\Client\Contacts\Model\CardChanged[]'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'syncToken' => null,
        'inserted' => null,
        'updated' => null,
        'deleted' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'syncToken' => 'syncToken',
        'inserted' => 'inserted',
        'updated' => 'updated',
        'deleted' => 'deleted'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'syncToken' => 'setSyncToken',
        'inserted' => 'setInserted',
        'updated' => 'setUpdated',
        'deleted' => 'setDeleted'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'syncToken' => 'getSyncToken',
        'inserted' => 'getInserted',
        'updated' => 'getUpdated',
        'deleted' => 'getDeleted'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['syncToken'] = isset($data['syncToken']) ? $data['syncToken'] : null;
        $this->container['inserted'] = isset($data['inserted']) ? $data['inserted'] : null;
        $this->container['updated'] = isset($data['updated']) ? $data['updated'] : null;
        $this->container['deleted'] = isset($data['deleted']) ? $data['deleted'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['syncToken'] === null) {
            $invalidProperties[] = "'syncToken' can't be null";
        }
        if ($this->container['inserted'] === null) {
            $invalidProperties[] = "'inserted' can't be null";
        }
        if ($this->container['updated'] === null) {
            $invalidProperties[] = "'updated' can't be null";
        }
        if ($this->container['deleted'] === null) {
            $invalidProperties[] = "'deleted' can't be null";
        }
        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {

        if ($this->container['syncToken'] === null) {
            return false;
        }
        if ($this->container['inserted'] === null) {
            return false;
        }
        if ($this->container['updated'] === null) {
            return false;
        }
        if ($this->container['deleted'] === null) {
            return false;
        }
        return true;
    }


    /**
     * Gets syncToken
     *
     * @return string
     */
    public function getSyncToken()
    {
        return $this->container['syncToken'];
    }

    /**
     * Sets syncToken
     *
     * @param string $syncToken Current sync token
     *
     * @return $this
     */
    public function setSyncToken($syncToken)
    {
        $this->container['syncToken'] = $syncToken;

        return $this;
    }

    /**
     * Gets inserted
     *
     * @return \WT\Client\Contacts\Model\CardChanged[]
     */
    public function getInserted()
    {
        return $this->container['inserted'];
    }

    /**
     * Sets inserted
     *
     * @param \WT\Client\Contacts\Model\CardChanged[] $inserted Items that have been inserted
     *
     * @return $this
     */
    public function setInserted($inserted)
    {
        $this->container['inserted'] = $inserted;

        return $this;
    }

    /**
     * Gets updated
     *
     * @return \WT\Client\Contacts\Model\CardChanged[]
     */
    public function getUpdated()
    {
        return $this->container['updated'];
    }

    /**
     * Sets updated
     *
     * @param \WT\Client\Contacts\Model\CardChanged[] $updated Items that have been updated
     *
     * @return $this
     */
    public function setUpdated($updated)
    {
        $this->container['updated'] = $updated;

        return $this;
    }

    /**
     * Gets deleted
     *
     * @return \WT\Client\Contacts\Model\CardChanged[]
     */
    public function getDeleted()
    {
        return $this->container['deleted'];
    }

    /**
     * Sets deleted
     *
     * @param \WT\Client\Contacts\Model\CardChanged[] $deleted Items that have been deleted
     *
     * @return $this
     */
    public function setDeleted($deleted)
    {
        $this->container['deleted'] = $deleted;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}

