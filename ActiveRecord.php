<?php
/**
 * Just a helper class that allows you to "accept all attributes" without having to define the functions for it every time.
 * All attributes not being assigned to a public property or via a setter are considered "dynamic"
 *
 * @author    Steve Guns <steve@bedezign.com>
 * @package   com.bedezign.yii2.mongodb
 * @copyright 2014 B&E DeZign
 *
 * @todo Add "parseFromClassDocBlock for the properties
 */

namespace bedezign\yii2\mongodb;

class ActiveRecord extends \yii\mongodb\ActiveRecord
{
	/**
	 * @var string List of "dynamically added" (not public properties) attributes
	 */
	protected        $attributes  = [];
	protected static $_properties = [];

	public function addAttribute($name, $defaultValue = null)
	{
		if (!$this->hasAttribute($name))
			$this->attributes[$name] = $defaultValue;
	}

	public function addAttributes($names)
	{
		foreach ($names as $name)
			$this->addAttribute($name);
	}

	/**
	 * Determines if an ActiveRecord has the specified property
	 *
	 * @param string $name
	 * @param bool $dynamicOnly      if true it will only check the dynamically added attributes, not the public ones
	 * @return bool
	 */
	public function hasAttribute($name, $dynamicOnly = false)
	{
		if (array_key_exists($name, $this->attributes))
			return true;
		return $dynamicOnly ? false : parent::hasAttribute($name);
	}

	public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
	{
		if ($this->hasAttribute($name, true)) return true;
		return parent::canSetProperty($name, $checkVars, $checkBehaviors);
	}

	/**
	 * Fetches the class level doc-block and extracts all @property and @property-write tags.
	 * If they are in the format <type> <$property> then they are considered a magicProperty
	 *
	 * @return string['property' => 'type']
	 */
	public static function magicProperties()
	{
		$class = get_called_class();
		if (!isset(static::$_properties[$class])) {
			$reflection = new \ReflectionClass($class);

			$properties = [];
			$docBlock = $reflection->getDocComment();
			if ($docBlock) {
				$pattern = '#^\s*\*\s+@property(?:-(\w*)){0,1}\s+(\S+)\s+(\$\S+).*$#im';
				if (preg_match_all($pattern, $docBlock, $matches, PREG_SET_ORDER))
					foreach ($matches as $match) {
						if (empty($match[1]) || strtolower($match[1] == 'write')) {
							// Only support writable properties
							$properties[trim($match[3], '$')] = $match[2];
						}
					}
				}

			if (!array_key_exists('_id', $properties))
				$properties ['_id'] = 'MongoId';

			static::$_properties[$class] = $properties;
		}

		return static::$_properties[$class];
	}

	/**
	 * Overridden to make it easier on you, it will automatically return the public properties of the class (+ "_id")
	 * merged with whatever you allocated dynamically
	 * @return array
	 */
	public function attributes()
	{
		return array_merge(
			// Whatever magic was defined
			array_keys(static::magicProperties()),
			// Whatever "@runtime" was added
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
		if ($row) {
			$dynamic = array_diff(array_keys($row), $record->attributes());
			$record->addAttributes($dynamic);
		}
		parent::populateRecord($record, $row);
	}
}