<?php
/**
 * Just a helper class that allows you to "accept all attributes" without having to define the functions for it every time.
 * All attributes not being assigned to a public property or via a setter are considered "dynamic".
 *
 * @author    Steve Guns <steve@bedezign.com>
 * @package   com.bedezign.yii2.mongodb
 * @copyright 2014 B&E DeZign
 *
 * @todo Add "parseFromClassDocBlock" for the properties
 * @todo Type juggling, since we have the information on a correct docblock
 */

namespace bedezign\yii2\mongodb;

class ActiveRecord extends \yii\mongodb\ActiveRecord
{
	/**
	 * @var string List of "dynamically added" (not public properties) attributes
	 */
	protected        $attributes  = [];
	/**
	 * @var array  "cache" of all models for which the docblock was parsed already
	 */
	protected static $_properties = [];

	public function init()
	{
		$this->attributes = static::magicProperties();
		parent::init();
	}

	/**
	 * Add an attribute to make sure it is recognised from now on.
	 * This function is for truly dynamic properties, not even declared in the docblock
	 *
	 * @param $name
	 * @param null $defaultValue
	 */
	public function addAttribute($name, $defaultValue = null)
	{
		if (!$this->hasAttribute($name)) {
			$this->attributes[$name] = 'mixed';
			$this->setAttribute($name, $defaultValue);
		}
	}

	public function addAttributes($names)
	{
		foreach ($names as $name)
			$this->addAttribute($name);
	}

	/**
	 * Determines if an ActiveRecord has the specified property (in its default mode its pretty useless unless i
	 * @param string $name
	 * @param bool $dynamicOnly      if true it will only check the dynamically added attributes, not the public ones
	 * @return bool
	 */
	public function hasAttribute($name, $dynamicOnly = false)
	{
		if ($dynamicOnly) {
			return array_key_exists($name, $this->attributes);
		}
		return $dynamicOnly ? false : parent::hasAttribute($name);
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
	 * Returns all dynamic attributes.
	 * @return array
	 */
	public function attributes()
	{
		return array_keys($this->attributes);
	}

	/**
	 * Regular populate overridden to determine the dynamic attributes first and add those.
	 * After that the populate can continue normally. Please note that this will pickup ANY
	 * attribute not returned as a regular one, even if it wasn't added as magic property
	 *
	 * @param \yii\db\BaseActiveRecord $record
	 * @param array $row
	 */
	public static function populateRecord($record, $row)
	{
		if ($row) {
			// Just figure out the ones we don't already know about and register them so they also get picked up
			$dynamic = array_diff(array_keys($row), $record->attributes());
			$record->addAttributes($dynamic);
		}
		// Now let the regular code do its job
		parent::populateRecord($record, $row);
	}
}