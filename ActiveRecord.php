<?php
/**
 * Improves the Yii2 MongoDB ActiveRecord class a little bit by allowing it to have "dynamic attributes".
 * Also, no need to implement the "attributes()" function if you are just using the public properties.
 *
 * @author    Steve Guns <steve@bedezign.com>
 * @package   com.bedezign.yii2.mongodb
 * @copyright 2014 B&E DeZign
 */

namespace bedezign\yii2\mongodb;

class ActiveRecord extends \yii\mongodb\ActiveRecord
{
	protected        $attributes = [];
	protected static $_properties       = null;

	public function addAttribute($name)
	{
		$this->attributes[$name] = null;
	}

	public function addAttributes($names)
	{
		foreach ($names as $name)
			$this->addAttribute($name);
	}

	/**
	 * Determines if an activerecord has the specified property
	 *
	 * @param string $name
	 * @param bool $dynamicOnly      if true it will only check the dynamically added attributes, not the regular ones
	 * @return bool
	 */
	public function hasAttribute($name, $dynamicOnly = false)
	{
		if (array_key_exists($name, $this->attributes))
			return true;

		return $dynamicOnly ? false : parent::hasAttribute($name);
	}

	/**
	 * Determines the public properties for the model and returns them.
	 * They are cached at class level.
	 *
	 * @return string[]
	 */
	public static function publicProperties()
	{
		if (static::$_properties === null) {
			$class = new \ReflectionClass(get_called_class());
			$properties = [];
			foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property)
				if (!$property->isStatic())
					$properties[] = $property->getName();

			if (!in_array('_id', $properties))
				array_unshift($properties, '_id');

			static::$_properties = $properties;
		}

		return static::$_properties;
	}

	/**
	 * Overridden to make it easier on you, it will automatically return the public properties of the class (+ "_id")
	 * merged with whatever you allocated dynamically
	 * @return array
	 */
	public function attributes()
	{
		return array_merge(
			static::publicProperties(),
			array_keys($this->attributes)
		);
	}

	/**
	 * Regular populate overridden to determine the dynamic attributes first and add those.
	 * After that the populate can continue normally
	 *
	 * @param \yii\db\BaseActiveRecord $record
	 * @param array $row
	 */
	public static function populateRecord($record, $row)
	{
		// Just figure out the dynamic attributes and then let the code work the regular way
		$dynamic = array_diff(array_keys($row), $row->attributes());
		$row->addAttributes($dynamic);

		parent::populateRecord($record, $row);
	}

	public function setAttribute($name, $value)
	{
		if (!$this->hasAttribute($name))
			$this->addAttribute($name);

		$this->$name = $value;
	}

	public function __get($name)
	{
		if ($this->hasAttribute($name, true))
			return $this->attributes[$name];
		else
			return parent::__get($name);
	}

	public function __set($name, $value)
	{
		if ($this->hasAttribute($name, true))
			$this->attributes[$name] = $value;
		else
			parent::__set($name, $value);
	}

	public function __unset($name)
	{
		if ($this->hasAttribute($name, true))
			unset($this->attributes[$name]);
		else
			parent::__unset($name);
	}

}