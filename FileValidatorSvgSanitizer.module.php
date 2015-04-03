<?php

/**
 * Class FileValidatorSvgSanitizer
 *
 * Validates and/or sanitizes SVG files, for ProcessWire 2.5.25 or newer.
 *
 * Optionally override any settings in your /site/config.php file: 
 *
 * $config->FileValidatorSvgSanitizer = array(
 * 
 *	// override global whitelist (see whitelist.json for default)
 *	'whitelist' => array or JSON string,
 * 
 *	// override whitelist on a per-field basis (replace "fieldname"):
 *	'whitelist_fieldname' => array or JSON string, 
 * 
 *	// override sanitize vs. validate on a per-field basis (replace "fieldname"):
 *	// note that default behavior is configured with module interactively
 *	'sanitize_fieldname' => true to allow sanitize, or false to disallow
 * );
 *
 * @property int|bool $allowSanitize
 * @property string $whitelist JSON whitelist override
 *
 */
class FileValidatorSvgSanitizer extends FileValidatorModule {

	public static function getModuleInfo() {
		return array(
			'title' => 'Validate SVG files',
			'summary' => 'Validates and/or sanitizes SVG files.', 
			'version' => 1, 
			'author' => 'Adrian and Ryan',
			'autoload' => false, 
			'singular' => false, 
			'validates' => array('svg')
		);
	}

	/**
	 * Cached instance of SVGSanitizer
	 *
	 */
	protected $svgSanitizer = null;

	/**
	 * Generate SVG Sanitizer settings object
	 *
	 */
	public function __construct() {
		require_once(dirname(__FILE__) . '/svgsanitizer/SvgSanitizer.php');
	}

	/**
	 * Get the SVGSanitizer instance
	 *
	 * @return SVGSanitizer
	 *
	 */
	public function getSvgSanitizer() {
		if(is_null($this->svgSanitizer)) $this->svgSanitizer = new SvgSanitizer();
		return $this->svgSanitizer;
	}

	/**
	 * Sanitize the given dirty SVG and return the clean SVG
	 *
	 * @param string Dirty SVG
	 * @param array|string|null $whitelist Optional JSON or array, or omit to use default
 	 * @return string Clean SVG
	 *
	 */
	public function svg($filename, $whitelist = null) {
		if($whitelist && !is_array($whitelist)) {
			// assumed to be a JSON string that needs decoding
			$whitelist = json_decode($whitelist, true);
		}
		if(!$whitelist) {
			// if no whitelist provided, or above json_decode fails, use default
			$whitelist = $this->getDefaultWhitelist();
		}
		$this->getSvgSanitizer()->load($filename);
		$this->getSvgSanitizer()->sanitize($whitelist);
		return $this->getSvgSanitizer()->saveSVG();
	}

	/**
	 * Return the data from the default whitelist (whitelist.json)
	 *
	 * @return array
	 *
	 */
	public function getDefaultWhitelist() {
		return json_decode(file_get_contents(dirname(__FILE__) . '/whitelist.json'), true);
	}

	/**
	 * Is the given SVG file valid? 
	 *
	 * This is for implementation of PW's FileValidator interface. 
	 * 
	 * This method should return:
	 * - boolean TRUE if file is valid
	 * - boolean FALSE if file is not valid
	 * - integer 1 if file is valid as a result of sanitization performed by this method
	 * 	
	 * If method wants to explain why the file is not valid, it should call $this->error('reason why not valid'). 
	 * 
	 * @param string $filename Full path and filename to the file
	 * @return bool|int
	 * 
	 */
	protected function isValidFile($filename) {

		$whitelist = null;
		$allowSanitize = $this->allowSanitize; 
		$overrides = $this->wire('config')->FileValidatorSvgSanitizer;
		$field = $this->getField();

		if(is_array($overrides)) {
			// $config->FileValidatorSvgSanitizer settings array is present
			if($field) {
				// this isValidFile call is working with a specific field
				// use optional field-level overrides for whitelist and sanitize
				$whitelist = $overrides->{"whitelist_$field->name"};
				$allowSanitize = $overrides->{"sanitize_$field->name"};
			}
			// optional global override for whitelist
			if(is_null($whitelist)) $whitelist = $overrides->whitelist;
		}	

		// use value configured with module if not overridden
		if(is_null($allowSanitize)) $allowSanitize = $this->allowSanitize; 

		$changed = $this->svg($filename, $whitelist); 

		if($changed) {
			// SVG file was modified
			if($this->allowSanitize) {
				// sanitization is allowed: return integer 1
				return 1;
			} else {
				// sanitization it not allowed, so file is invalid
				return false;
			}
		} 

		// no changes necessary to file, so it is valid
		return true; 
	}


}

