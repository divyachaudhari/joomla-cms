<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Language;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\String\StringHelper;

/**
 * Languages/translation handler class
 *
 * @since  1.7.0
 */
class Language
{
	/**
	 * Array of Language objects
	 *
	 * @var    Language[]
	 * @since  1.7.0
	 */
	protected static $languages = array();

	/**
	 * Debug language, If true, highlights if string isn't found.
	 *
	 * @var    boolean
	 * @since  1.7.0
	 */
	protected $debug = false;

	/**
	 * The default language, used when a language file in the requested language does not exist.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $default = 'en-GB';

	/**
	 * An array of orphaned text.
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $orphans = array();

	/**
	 * Array holding the language metadata.
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $metadata = null;

	/**
	 * Array holding the language locale or boolean null if none.
	 *
	 * @var    array|boolean
	 * @since  1.7.0
	 */
	protected $locale = null;

	/**
	 * The language to load.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $lang = null;

	/**
	 * A nested array of language files that have been loaded
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $paths = array();

	/**
	 * List of language files that are in error state
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $errorfiles = array();

	/**
	 * Translations
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $strings = array();

	/**
	 * An array of used text, used during debugging.
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $used = array();

	/**
	 * Counter for number of loads.
	 *
	 * @var    integer
	 * @since  1.7.0
	 */
	protected $counter = 0;

	/**
	 * An array used to store overrides.
	 *
	 * @var    array
	 * @since  1.7.0
	 */
	protected $override = array();

	/**
	 * Name of the transliterator function for this language.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $transliterator = null;

	/**
	 * Name of the pluralSuffixesCallback function for this language.
	 *
	 * @var    callable
	 * @since  1.7.0
	 */
	protected $pluralSuffixesCallback = null;

	/**
	 * Name of the ignoredSearchWordsCallback function for this language.
	 *
	 * @var    callable
	 * @since  1.7.0
	 */
	protected $ignoredSearchWordsCallback = null;

	/**
	 * Name of the lowerLimitSearchWordCallback function for this language.
	 *
	 * @var    callable
	 * @since  1.7.0
	 */
	protected $lowerLimitSearchWordCallback = null;

	/**
	 * Name of the upperLimitSearchWordCallback function for this language.
	 *
	 * @var    callable
	 * @since  1.7.0
	 */
	protected $upperLimitSearchWordCallback = null;

	/**
	 * Name of the searchDisplayedCharactersNumberCallback function for this language.
	 *
	 * @var    callable
	 * @since  1.7.0
	 */
	protected $searchDisplayedCharactersNumberCallback = null;

	/**
	 * Constructor activating the default information of the language.
	 *
	 * @param   string   $lang   The language
	 * @param   boolean  $debug  Indicates if language debugging is enabled.
	 *
	 * @since   1.7.0
	 */
	public function __construct($lang = null, $debug = false)
	{
		$this->strings = array();

		if ($lang == null)
		{
			$lang = $this->default;
		}

		$this->lang = $lang;
		$this->metadata = LanguageHelper::getMetadata($this->lang);
		$this->setDebug($debug);

		$this->override = $this->parse(JPATH_BASE . '/language/overrides/' . $lang . '.override.ini');

		// Look for a language specific localise class
		$class = str_replace('-', '_', $lang . 'Localise');
		$paths = array();

		if (defined('JPATH_SITE'))
		{
			// Note: Manual indexing to enforce load order.
			$paths[0] = JPATH_SITE . "/language/overrides/$lang.localise.php";
			$paths[2] = JPATH_SITE . "/language/$lang/$lang.localise.php";
		}

		if (defined('JPATH_ADMINISTRATOR'))
		{
			// Note: Manual indexing to enforce load order.
			$paths[1] = JPATH_ADMINISTRATOR . "/language/overrides/$lang.localise.php";
			$paths[3] = JPATH_ADMINISTRATOR . "/language/$lang/$lang.localise.php";
		}

		ksort($paths);
		$path = reset($paths);

		while (!class_exists($class) && $path)
		{
			if (file_exists($path))
			{
				require_once $path;
			}

			$path = next($paths);
		}

		if (class_exists($class))
		{
			/**
			 * Class exists. Try to find
			 * -a transliterate method,
			 * -a getPluralSuffixes method,
			 * -a getIgnoredSearchWords method
			 * -a getLowerLimitSearchWord method
			 * -a getUpperLimitSearchWord method
			 * -a getSearchDisplayCharactersNumber method
			 */
			if (method_exists($class, 'transliterate'))
			{
				$this->transliterator = array($class, 'transliterate');
			}

			if (method_exists($class, 'getPluralSuffixes'))
			{
				$this->pluralSuffixesCallback = array($class, 'getPluralSuffixes');
			}

			if (method_exists($class, 'getIgnoredSearchWords'))
			{
				$this->ignoredSearchWordsCallback = array($class, 'getIgnoredSearchWords');
			}

			if (method_exists($class, 'getLowerLimitSearchWord'))
			{
				$this->lowerLimitSearchWordCallback = array($class, 'getLowerLimitSearchWord');
			}

			if (method_exists($class, 'getUpperLimitSearchWord'))
			{
				$this->upperLimitSearchWordCallback = array($class, 'getUpperLimitSearchWord');
			}

			if (method_exists($class, 'getSearchDisplayedCharactersNumber'))
			{
				$this->searchDisplayedCharactersNumberCallback = array($class, 'getSearchDisplayedCharactersNumber');
			}
		}

		$this->load();
	}

	/**
	 * Returns a language object.
	 *
	 * @param   string   $lang   The language to use.
	 * @param   boolean  $debug  The debug mode.
	 *
	 * @return  Language  The Language object.
	 *
	 * @since       1.7.0
	 * @deprecated  5.0 Use the language factory instead
	 */
	public static function getInstance($lang, $debug = false)
	{
		if (!isset(self::$languages[$lang . $debug]))
		{
			self::$languages[$lang . $debug] = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($lang, $debug);
		}

		return self::$languages[$lang . $debug];
	}

	/**
	 * Translate function, mimics the php gettext (alias _) function.
	 *
	 * The function checks if $jsSafe is true, then if $interpretBackslashes is true.
	 *
	 * @param   string   $string                The string to translate
	 * @param   boolean  $jsSafe                Make the result javascript safe
	 * @param   boolean  $interpretBackSlashes  Interpret \t and \n
	 *
	 * @return  string  The translation of the string
	 *
	 * @since   1.7.0
	 */
	public function _($string, $jsSafe = false, $interpretBackSlashes = true)
	{
		// Detect empty string
		if ($string == '')
		{
			return '';
		}

		$key = strtoupper($string);

		if (isset($this->strings[$key]))
		{
			$string = $this->strings[$key];

			// Store debug information
			if ($this->debug)
			{
				$value = \JFactory::getApplication()->get('debug_lang_const') == 0 ? $key : $string;
				$string = '**' . $value . '**';

				$caller = $this->getCallerInfo();

				if (!array_key_exists($key, $this->used))
				{
					$this->used[$key] = array();
				}

				$this->used[$key][] = $caller;
			}
		}
		else
		{
			if ($this->debug)
			{
				$info = [];
				$info['trace'] = $this->getTrace();
				$info['key'] = $key;
				$info['string'] = $string;

				if (!array_key_exists($key, $this->orphans))
				{
					$this->orphans[$key] = array();
				}

				$this->orphans[$key][] = $info;

				$string = '??' . $string . '??';
			}
		}

		if ($jsSafe)
		{
			// Javascript filter
			$string = addslashes($string);
		}
		elseif ($interpretBackSlashes)
		{
			if (strpos($string, '\\') !== false)
			{
				// Interpret \n and \t characters
				$string = str_replace(array('\\\\', '\t', '\n'), array("\\", "\t", "\n"), $string);
			}
		}

		return $string;
	}

	/**
	 * Transliterate function
	 *
	 * This method processes a string and replaces all accented UTF-8 characters by unaccented
	 * ASCII-7 "equivalents".
	 *
	 * @param   string  $string  The string to transliterate.
	 *
	 * @return  string  The transliteration of the string.
	 *
	 * @since   1.7.0
	 */
	public function transliterate($string)
	{
		if ($this->transliterator !== null)
		{
			return call_user_func($this->transliterator, $string);
		}

		$string = Transliterate::utf8_latin_to_ascii($string);
		$string = StringHelper::strtolower($string);

		return $string;
	}

	/**
	 * Getter for transliteration function
	 *
	 * @return  callable  The transliterator function
	 *
	 * @since   1.7.0
	 */
	public function getTransliterator()
	{
		return $this->transliterator;
	}

	/**
	 * Set the transliteration function.
	 *
	 * @param   callable  $function  Function name or the actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setTransliterator(callable $function)
	{
		$previous = $this->transliterator;
		$this->transliterator = $function;

		return $previous;
	}

	/**
	 * Returns an array of suffixes for plural rules.
	 *
	 * @param   integer  $count  The count number the rule is for.
	 *
	 * @return  array    The array of suffixes.
	 *
	 * @since   1.7.0
	 */
	public function getPluralSuffixes($count)
	{
		if ($this->pluralSuffixesCallback !== null)
		{
			return call_user_func($this->pluralSuffixesCallback, $count);
		}
		else
		{
			return array((string) $count);
		}
	}

	/**
	 * Getter for pluralSuffixesCallback function.
	 *
	 * @return  callable  Function name or the actual function.
	 *
	 * @since   1.7.0
	 */
	public function getPluralSuffixesCallback()
	{
		return $this->pluralSuffixesCallback;
	}

	/**
	 * Set the pluralSuffixes function.
	 *
	 * @param   callable  $function  Function name or actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setPluralSuffixesCallback(callable $function)
	{
		$previous = $this->pluralSuffixesCallback;
		$this->pluralSuffixesCallback = $function;

		return $previous;
	}

	/**
	 * Returns an array of ignored search words
	 *
	 * @return  array  The array of ignored search words.
	 *
	 * @since   1.7.0
	 */
	public function getIgnoredSearchWords()
	{
		if ($this->ignoredSearchWordsCallback !== null)
		{
			return call_user_func($this->ignoredSearchWordsCallback);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Getter for ignoredSearchWordsCallback function.
	 *
	 * @return  callable  Function name or the actual function.
	 *
	 * @since   1.7.0
	 */
	public function getIgnoredSearchWordsCallback()
	{
		return $this->ignoredSearchWordsCallback;
	}

	/**
	 * Setter for the ignoredSearchWordsCallback function
	 *
	 * @param   callable  $function  Function name or actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setIgnoredSearchWordsCallback(callable $function)
	{
		$previous = $this->ignoredSearchWordsCallback;
		$this->ignoredSearchWordsCallback = $function;

		return $previous;
	}

	/**
	 * Returns a lower limit integer for length of search words
	 *
	 * @return  integer  The lower limit integer for length of search words (3 if no value was set for a specific language).
	 *
	 * @since   1.7.0
	 */
	public function getLowerLimitSearchWord()
	{
		if ($this->lowerLimitSearchWordCallback !== null)
		{
			return call_user_func($this->lowerLimitSearchWordCallback);
		}
		else
		{
			return 3;
		}
	}

	/**
	 * Getter for lowerLimitSearchWordCallback function
	 *
	 * @return  callable  Function name or the actual function.
	 *
	 * @since   1.7.0
	 */
	public function getLowerLimitSearchWordCallback()
	{
		return $this->lowerLimitSearchWordCallback;
	}

	/**
	 * Setter for the lowerLimitSearchWordCallback function.
	 *
	 * @param   callable  $function  Function name or actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setLowerLimitSearchWordCallback(callable $function)
	{
		$previous = $this->lowerLimitSearchWordCallback;
		$this->lowerLimitSearchWordCallback = $function;

		return $previous;
	}

	/**
	 * Returns an upper limit integer for length of search words
	 *
	 * @return  integer  The upper limit integer for length of search words (200 if no value was set or if default value is < 200).
	 *
	 * @since   1.7.0
	 */
	public function getUpperLimitSearchWord()
	{
		if ($this->upperLimitSearchWordCallback !== null && call_user_func($this->upperLimitSearchWordCallback) > 200)
		{
			return call_user_func($this->upperLimitSearchWordCallback);
		}

		return 200;
	}

	/**
	 * Getter for upperLimitSearchWordCallback function
	 *
	 * @return  callable  Function name or the actual function.
	 *
	 * @since   1.7.0
	 */
	public function getUpperLimitSearchWordCallback()
	{
		return $this->upperLimitSearchWordCallback;
	}

	/**
	 * Setter for the upperLimitSearchWordCallback function
	 *
	 * @param   callable  $function  Function name or the actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setUpperLimitSearchWordCallback(callable $function)
	{
		$previous = $this->upperLimitSearchWordCallback;
		$this->upperLimitSearchWordCallback = $function;

		return $previous;
	}

	/**
	 * Returns the number of characters displayed in search results.
	 *
	 * @return  integer  The number of characters displayed (200 if no value was set for a specific language).
	 *
	 * @since   1.7.0
	 */
	public function getSearchDisplayedCharactersNumber()
	{
		if ($this->searchDisplayedCharactersNumberCallback !== null)
		{
			return call_user_func($this->searchDisplayedCharactersNumberCallback);
		}
		else
		{
			return 200;
		}
	}

	/**
	 * Getter for searchDisplayedCharactersNumberCallback function
	 *
	 * @return  callable  Function name or the actual function.
	 *
	 * @since   1.7.0
	 */
	public function getSearchDisplayedCharactersNumberCallback()
	{
		return $this->searchDisplayedCharactersNumberCallback;
	}

	/**
	 * Setter for the searchDisplayedCharactersNumberCallback function.
	 *
	 * @param   callable  $function  Function name or the actual function.
	 *
	 * @return  callable  The previous function.
	 *
	 * @since   1.7.0
	 */
	public function setSearchDisplayedCharactersNumberCallback(callable $function)
	{
		$previous = $this->searchDisplayedCharactersNumberCallback;
		$this->searchDisplayedCharactersNumberCallback = $function;

		return $previous;
	}

	/**
	 * Loads a single language file and appends the results to the existing strings
	 *
	 * @param   string   $extension  The extension for which a language file should be loaded.
	 * @param   string   $basePath   The basepath to use.
	 * @param   string   $lang       The language to load, default null for the current language.
	 * @param   boolean  $reload     Flag that will force a language to be reloaded if set to true.
	 * @param   boolean  $default    Flag that force the default language to be loaded if the current does not exist.
	 *
	 * @return  boolean  True if the file has successfully loaded.
	 *
	 * @since   1.7.0
	 */
	public function load($extension = 'joomla', $basePath = JPATH_BASE, $lang = null, $reload = false, $default = true)
	{
		// If language is null set as the current language.
		if (!$lang)
		{
			$lang = $this->lang;
		}

		// Load the default language first if we're not debugging and a non-default language is requested to be loaded
		// with $default set to true
		if (!$this->debug && ($lang != $this->default) && $default)
		{
			$this->load($extension, $basePath, $this->default, false, true);
		}

		$path = LanguageHelper::getLanguagePath($basePath, $lang);

		$internal = $extension == 'joomla' || $extension == '';
		$filename = $internal ? $lang : $lang . '.' . $extension;
		$filename = "$path/$filename.ini";

		if (isset($this->paths[$extension][$filename]) && !$reload)
		{
			// This file has already been tested for loading.
			$result = $this->paths[$extension][$filename];
		}
		else
		{
			// Load the language file
			$result = $this->loadLanguage($filename, $extension);

			// Check whether there was a problem with loading the file
			if ($result === false && $default)
			{
				// No strings, so either file doesn't exist or the file is invalid
				$oldFilename = $filename;

				// Check the standard file name
				if (!$this->debug)
				{
					$path = LanguageHelper::getLanguagePath($basePath, $this->default);

					$filename = $internal ? $this->default : $this->default . '.' . $extension;
					$filename = "$path/$filename.ini";

					// If the one we tried is different than the new name, try again
					if ($oldFilename != $filename)
					{
						$result = $this->loadLanguage($filename, $extension, false);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Loads a language file.
	 *
	 * This method will not note the successful loading of a file - use load() instead.
	 *
	 * @param   string  $fileName   The name of the file.
	 * @param   string  $extension  The name of the extension.
	 *
	 * @return  boolean  True if new strings have been added to the language
	 *
	 * @see     Language::load()
	 * @since   1.7.0
	 */
	protected function loadLanguage($fileName, $extension = 'unknown')
	{
		$this->counter++;

		$result  = false;
		$strings = $this->parse($fileName);

		if ($strings !== array())
		{
			$this->strings = array_replace($this->strings, $strings, $this->override);
			$result = true;
		}

		// Record the result of loading the extension's file.
		if (!isset($this->paths[$extension]))
		{
			$this->paths[$extension] = array();
		}

		$this->paths[$extension][$fileName] = $result;

		return $result;
	}

	/**
	 * Parses a language file.
	 *
	 * @param   string  $fileName  The name of the file.
	 *
	 * @return  array  The array of parsed strings.
	 *
	 * @since   1.7.0
	 */
	protected function parse($fileName)
	{
		$strings = LanguageHelper::parseIniFile($fileName, $this->debug);

		// Debug the ini file if needed.
		if ($this->debug === true && file_exists($fileName))
		{
			$this->debugFile($fileName);
		}

		return $strings;
	}

	/**
	 * Debugs a language file
	 *
	 * @param   string  $filename  Absolute path to the file to debug
	 *
	 * @return  integer  A count of the number of parsing errors
	 *
	 * @since   3.6.3
	 * @throws  \InvalidArgumentException
	 */
	public function debugFile($filename)
	{
		// Make sure our file actually exists
		if (!file_exists($filename))
		{
			throw new \InvalidArgumentException(
				sprintf('Unable to locate file "%s" for debugging', $filename)
			);
		}

		// Initialise variables for manually parsing the file for common errors.
		$blacklist = array('YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE');
		$debug = $this->getDebug();
		$this->debug = false;
		$errors = array();
		$php_errormsg = null;

		// Open the file as a stream.
		$file = new \SplFileObject($filename);

		foreach ($file as $lineNumber => $line)
		{
			// Avoid BOM error as BOM is OK when using parse_ini.
			if ($lineNumber == 0)
			{
				$line = str_replace("\xEF\xBB\xBF", '', $line);
			}

			$line = trim($line);

			// Ignore comment lines.
			if (!strlen($line) || $line['0'] == ';')
			{
				continue;
			}

			// Ignore grouping tag lines, like: [group]
			if (preg_match('#^\[[^\]]*\](\s*;.*)?$#', $line))
			{
				continue;
			}

			$realNumber = $lineNumber + 1;

			// Check for odd number of double quotes.
			if (substr_count($line, '"') % 2 != 0)
			{
				$errors[] = $realNumber;
				continue;
			}

			// Check that the line passes the necessary format.
			if (!preg_match('#^[A-Z][A-Z0-9_\*\-\.]*\s*=\s*".*"(\s*;.*)?$#', $line))
			{
				$errors[] = $realNumber;
				continue;
			}

			// Check that the key is not in the blacklist.
			$key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

			if (in_array($key, $blacklist))
			{
				$errors[] = $realNumber;
			}
		}

		// Check if we encountered any errors.
		if (count($errors))
		{
			$this->errorfiles[$filename] = $errors;
		}
		elseif ($php_errormsg)
		{
			// We didn't find any errors but there's probably a parse notice.
			$this->errorfiles['PHP' . $filename] = 'PHP parser errors :' . $php_errormsg;
		}

		$this->debug = $debug;

		return count($errors);
	}

	/**
	 * Get a metadata language property.
	 *
	 * @param   string  $property  The name of the property.
	 * @param   mixed   $default   The default value.
	 *
	 * @return  mixed  The value of the property.
	 *
	 * @since   1.7.0
	 */
	public function get($property, $default = null)
	{
		if (isset($this->metadata[$property]))
		{
			return $this->metadata[$property];
		}

		return $default;
	}

	/**
	 * Get a back trace.
	 *
	 * @return array
	 *
	 * @since 4.0.0
	 */
	protected function getTrace()
	{
		return \function_exists('debug_backtrace') ?  debug_backtrace() : [];
	}

	/**
	 * Determine who called Language or Text.
	 *
	 * @return  array  Caller information.
	 *
	 * @since   1.7.0
	 */
	protected function getCallerInfo()
	{
		// Try to determine the source if none was provided
		if (!function_exists('debug_backtrace'))
		{
			return;
		}

		$backtrace = debug_backtrace();
		$info = array();

		// Search through the backtrace to our caller
		$continue = true;

		while ($continue && next($backtrace))
		{
			$step = current($backtrace);
			$class = @ $step['class'];

			// We're looking for something outside of language.php
			if ($class != self::class && $class != Text::class)
			{
				$info['function'] = @ $step['function'];
				$info['class'] = $class;
				$info['step'] = prev($backtrace);

				// Determine the file and name of the file
				$info['file'] = @ $step['file'];
				$info['line'] = @ $step['line'];

				$continue = false;
			}
		}

		return $info;
	}

	/**
	 * Getter for Name.
	 *
	 * @return  string  Official name element of the language.
	 *
	 * @since   1.7.0
	 */
	public function getName()
	{
		return $this->metadata['name'];
	}

	/**
	 * Get a list of language files that have been loaded.
	 *
	 * @param   string  $extension  An optional extension name.
	 *
	 * @return  array
	 *
	 * @since   1.7.0
	 */
	public function getPaths($extension = null)
	{
		if (isset($extension))
		{
			if (isset($this->paths[$extension]))
			{
				return $this->paths[$extension];
			}

			return;
		}
		else
		{
			return $this->paths;
		}
	}

	/**
	 * Get a list of language files that are in error state.
	 *
	 * @return  array
	 *
	 * @since   1.7.0
	 */
	public function getErrorFiles()
	{
		return $this->errorfiles;
	}

	/**
	 * Getter for the language tag (as defined in RFC 3066)
	 *
	 * @return  string  The language tag.
	 *
	 * @since   1.7.0
	 */
	public function getTag()
	{
		return $this->metadata['tag'];
	}

	/**
	 * Getter for the calendar type
	 *
	 * @return  string  The calendar type.
	 *
	 * @since   3.7.0
	 */
	public function getCalendar()
	{
		if (isset($this->metadata['calendar']))
		{
			return $this->metadata['calendar'];
		}
		else
		{
			return 'gregorian';
		}
	}

	/**
	 * Get the RTL property.
	 *
	 * @return  boolean  True is it an RTL language.
	 *
	 * @since   1.7.0
	 */
	public function isRtl()
	{
		return (bool) $this->metadata['rtl'];
	}

	/**
	 * Set the Debug property.
	 *
	 * @param   boolean  $debug  The debug setting.
	 *
	 * @return  boolean  Previous value.
	 *
	 * @since   1.7.0
	 */
	public function setDebug($debug)
	{
		$previous = $this->debug;
		$this->debug = (boolean) $debug;

		return $previous;
	}

	/**
	 * Get the Debug property.
	 *
	 * @return  boolean  True is in debug mode.
	 *
	 * @since   1.7.0
	 */
	public function getDebug()
	{
		return $this->debug;
	}

	/**
	 * Get the default language code.
	 *
	 * @return  string  Language code.
	 *
	 * @since   1.7.0
	 */
	public function getDefault()
	{
		return $this->default;
	}

	/**
	 * Set the default language code.
	 *
	 * @param   string  $lang  The language code.
	 *
	 * @return  string  Previous value.
	 *
	 * @since   1.7.0
	 */
	public function setDefault($lang)
	{
		$previous = $this->default;
		$this->default = $lang;

		return $previous;
	}

	/**
	 * Get the list of orphaned strings if being tracked.
	 *
	 * @return  array  Orphaned text.
	 *
	 * @since   1.7.0
	 */
	public function getOrphans()
	{
		return $this->orphans;
	}

	/**
	 * Get the list of used strings.
	 *
	 * Used strings are those strings requested and found either as a string or a constant.
	 *
	 * @return  array  Used strings.
	 *
	 * @since   1.7.0
	 */
	public function getUsed()
	{
		return $this->used;
	}

	/**
	 * Determines is a key exists.
	 *
	 * @param   string  $string  The key to check.
	 *
	 * @return  boolean  True, if the key exists.
	 *
	 * @since   1.7.0
	 */
	public function hasKey($string)
	{
		$key = strtoupper($string);

		return isset($this->strings[$key]);
	}

	/**
	 * Get the language locale based on current language.
	 *
	 * @return  array  The locale according to the language.
	 *
	 * @since   1.7.0
	 */
	public function getLocale()
	{
		if (!isset($this->locale))
		{
			$locale = str_replace(' ', '', $this->metadata['locale'] ?? '');

			if ($locale)
			{
				$this->locale = explode(',', $locale);
			}
			else
			{
				$this->locale = false;
			}
		}

		return $this->locale;
	}

	/**
	 * Get the first day of the week for this language.
	 *
	 * @return  integer  The first day of the week according to the language
	 *
	 * @since   1.7.0
	 */
	public function getFirstDay()
	{
		return (int) ($this->metadata['firstDay'] ?? 0);
	}

	/**
	 * Get the weekends days for this language.
	 *
	 * @return  string  The weekend days of the week separated by a comma according to the language
	 *
	 * @since   3.2
	 */
	public function getWeekEnd()
	{
		return $this->metadata['weekEnd'] ?? '0,6';
	}
}
