<?php

/**
 * Bootstrap file for PHPUnit tests
 * Emulates Rhymix CMS core classes and functions for isolated testing
 */

/**
 * BaseObject - Rhymix base object emulation
 */
class BaseObject
{
	public $error = 0;
	public $message = 'success';
	public $data = null;

	function __construct($error = 0, $message = 'success')
	{
		$this->error = $error;
		$this->message = $message;
	}

	function toBool()
	{
		return ($this->error == 0);
	}

	function getMessage()
	{
		return $this->message;
	}
}

/**
 * ModuleObject - Rhymix module object emulation
 */
class ModuleObject extends BaseObject
{
	public $module_info;
	public $module_path = '';
	public $grant;
	public $mid = '';
	protected $messages = [];
	protected $redirect_url = '';
	protected $template_path = '';
	protected $template_file = '';
	private static $instances = [];

	function __construct()
	{
		$this->module_info = new stdClass();
		$this->grant = new stdClass();
	}

	public static function getInstance()
	{
		$className = get_called_class();
		if (!isset(self::$instances[$className]))
		{
			self::$instances[$className] = new $className();
		}
		return self::$instances[$className];
	}

	public static function resetInstances()
	{
		self::$instances = [];
	}

	function setMessage($message)
	{
		$this->messages[] = $message;
	}

	function getLastMessage()
	{
		return end($this->messages) ?: '';
	}

	function setRedirectUrl($url)
	{
		$this->redirect_url = $url;
	}

	function getRedirectUrl()
	{
		return $this->redirect_url;
	}

	function setTemplatePath($path)
	{
		$this->template_path = $path;
	}

	function setTemplateFile($file)
	{
		$this->template_file = $file;
	}
}

/**
 * Context - Rhymix context emulation
 */
class Context
{
	private static $vars = [];

	public static function get($key)
	{
		return isset(self::$vars[$key]) ? self::$vars[$key] : null;
	}

	public static function set($key, $value)
	{
		self::$vars[$key] = $value;
	}

	public static function getRequestVars()
	{
		return (object)self::$vars;
	}

	public static function reset()
	{
		self::$vars = [];
	}
}

/**
 * DB - Rhymix database emulation
 */
class DB
{
	private static $instance;

	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	function isColumnExists($table, $column)
	{
		return true;
	}

	function addColumn($table, $column, $type, $size = 0, $default = '', $notnull = false)
	{
	}

	function dropColumn($table, $column)
	{
	}
}

/**
 * moduleModel - Rhymix module model emulation
 */
class moduleModel
{
	public static function getModuleInfoByModuleSrl($module_srl)
	{
		$info = new stdClass();
		$info->module_srl = $module_srl;
		return $info;
	}
}

/**
 * TestHelper - Utility class for tracking function calls in tests
 */
class TestHelper
{
	public static $queries = [];
	public static $pointCalls = [];
	public static $executeQueryResults = [];

	public static function reset()
	{
		self::$queries = [];
		self::$pointCalls = [];
		self::$executeQueryResults = [];
	}

	public static function setQueryResult($queryId, $result)
	{
		self::$executeQueryResults[$queryId] = $result;
	}
}

/**
 * executeQuery - Rhymix query execution emulation
 */
function executeQuery($queryId, $args = null)
{
	TestHelper::$queries[] = ['queryId' => $queryId, 'args' => $args];

	if (isset(TestHelper::$executeQueryResults[$queryId]))
	{
		$result = TestHelper::$executeQueryResults[$queryId];
		if (is_callable($result))
		{
			return $result($args);
		}
		return $result;
	}

	$output = new BaseObject();
	$output->data = null;
	return $output;
}

/**
 * pointController - Rhymix point controller emulation
 */
class pointController
{
	public static function setPoint($member_srl, $point, $mode)
	{
		TestHelper::$pointCalls[] = [
			'member_srl' => $member_srl,
			'point' => $point,
			'mode' => $mode,
		];
	}
}

/**
 * pointModel - Rhymix point model emulation
 */
class pointModel
{
	private static $points = [];

	public static function setTestPoint($member_srl, $point)
	{
		self::$points[$member_srl] = $point;
	}

	public static function getPoint($member_srl)
	{
		return isset(self::$points[$member_srl]) ? self::$points[$member_srl] : 10000;
	}

	public static function reset()
	{
		self::$points = [];
	}
}

/**
 * getNotEncodedUrl - Rhymix URL helper emulation
 */
function getNotEncodedUrl()
{
	return '';
}

// Include module files
require_once __DIR__ . '/../plusad.class.php';
require_once __DIR__ . '/../plusad.controller.php';
require_once __DIR__ . '/../plusad.model.php';
require_once __DIR__ . '/../plusad.view.php';
