<?php
/**
 * @author Chris Zuber <shgysk8zer0@gmail.com>
 * @copyright 2014, Chris Zuber
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @package core_shared
 * @version 2015-04-27
 */
if (! defined('PHP_VERSION_ID')) {
	$version = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));

	if (PHP_VERSION_ID < 50207) {
		define('PHP_MAJOR_VERSION',   $version[0]);
		define('PHP_MINOR_VERSION',   $version[1]);
		define('PHP_RELEASE_VERSION', $version[2]);
	}
	unset($version);
}

if (! function_exists('mb_strimwidth')) {
	function mb_strimwidth($str, $start, $width, $trimmarker = '', $encoding = '') {
		if (strlen($str) > $start - $width) {
			return substr($str, $start, $width) . $trimmarker;
		}
		return substr($str, $start, $width);
	}
}
if (! function_exists('http_parse_headers')) {
	function http_parse_headers($raw_headers)
	{
		$headers = array();
		$key = ''; // [+]

		foreach(explode("\n", $raw_headers) as $i => $h)
		{
			$h = explode(':', $h, 2);

			if (isset($h[1])) {
				if (!isset($headers[$h[0]])) {
					$headers[$h[0]] = trim($h[1]);
				} elseif (is_array($headers[$h[0]])) {
					$headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
				} else {
					$headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
				}

				$key = $h[0];
			} else {
				if (substr($h[0], 0, 1) == "\t") {
					$headers[$key] .= "\r\n\t".trim($h[0]);
				} elseif (!$key) {
					$headers[0] = trim($h[0]);
					trim($h[0]);
				}
			}
		}
		return $headers;
	}
}

/**
 * Prevents functions registering from executing multiple times {Opt-in}.
 *
 * @param   Callable $function Register with __FUNCTION__ magic constant
 * @return  boolean            Whether or not it has been executed already
 * @example if (first_run(__FUNCTION__)) {...}
 * @example if (!first_run(__FUNCTION__)) return;
 */
function first_run(Callable $function = null) {
	static $ran = [];
	if (in_array($function, $ran)) {
		return false;
	} else {
		$ran[] = $function;
		return true;
	}
}

/**
 * Initial configuration. Setup include_path, gather database
 * connection information, set undefined properties to
 * default values, start a new \shgysk8zer0\Core\session, and set nonce
 *
 * @param bool $session
 * @return array $info
 * @deprecated
 */
function config()
{
	return;
}
/**
 * Nearly an alias of setenv, except it functions more similarly to define.
 * Also sets $_SERVER because CLI does not seem to work with putenv
 *
 * @param  string $name  Name of environment variable
 * @param  string $value Value to set it to
 * @return bool
 * @see https://php.net/manual/en/function.putenv.php
 */
function setenv($name, $value = null)
{
	$name = strtoupper($name);
	$_SERVER[$name] = $value;
	return putenv("$name=$value");
}

/**
 * Read and parse a JSON file
 *
 * @param  string $file             Path to file to parse
 * @param  bool   $assoc            When TRUE, returned objects will be converted into associative arrays.
 * @param  bool   $use_include_path Whether or not to search include_path
 * @return mixed                    Array or object, dependign on $assoc
 */
function parse_json_file($file, $assoc = false, $use_include_path = false)
{
	return json_decode(file_get_contents($file, $use_include_path), $assoc);
}

/**
 * Sets typical environment vars (such as SERVER_NAME) not applicable to CLI
 * as read from $file.
 *
 * @param  string $file Config file to use
 * @return void
 */
function cli_init($config = 'server.json')
{
	if (PHP_SAPI === 'cli' and first_run(__FUNCTION__)) {
		$path = defined('BASE') ? BASE : dirname(__DIR__);
		$config = parse_json_file(join(DIRECTORY_SEPARATOR, [$path, 'config', $config]), true);
		if (! array_key_exists('DOCUMENT_ROOT', $config)) {
			$config['DOCUMENT_ROOT'] = rtrim($path, DIRECTORY_SEPARATOR);
			if (DIRECTORY_SEPARATOR !== '/') {
				$config['DOCUMENT_ROOT'] = str_replace(DIRECTORY_SEPARATOR, '/', $config['DOCUMENT_ROOT']);
			}
		}
		array_map(
			'setenv',
			array_keys($config),
			array_values($config)
		);
	}
}
/**
 * Sets autoloader, include_path, error_handler, etc
 *
 * @param  bool $session         Whether or not to start PHP session
 * @param  string $settings_file File to parse for site settings
 * @param  string $config_dir    Config file directory (added to include_path)
 * @return void
 */
function init($session = true, $settings_file = 'settings.json')
{
	if (! first_run(__FUNCTION__)) {
		return;
	}

	if (!defined('BASE')) {
		define('BASE', dirname(__DIR__));
	}

	$settings = \shgysk8zer0\Core\Resources\Parser::load($settings_file);

	if (@is_string($settings->path)) {
		set_include_path(get_include_path() . PATH_SEPARATOR . realpath($settings->path));
	} elseif (@is_array($settings->path)) {
		set_include_path(
			get_include_path() . PATH_SEPARATOR
			. join(PATH_SEPARATOR, array_map('realpath', $settings->path))
		);
	}

	if (@is_string($settings->charset)) {
		ini_set('default_charset', strtoupper($settings->charset));
	} else {
		ini_set('default_charset', 'UTF-8');
	}

	if (
		@is_object($settings->define)
		and $settings->define = get_object_vars($settings->define)
	) {
		array_map(
			'define',
			array_map('strtoupper', array_keys($settings->define)),
			array_values($settings->define)
		);
	}

	if (@is_array($settings->requires)) {
		array_map(function($path)
		{
			require_once BASE . DIRECTORY_SEPARATOR . $path;
		}, $settings->requires);
	}

	if (@is_string($settings->time_zone)) {
		date_default_timezone_set($settings->time_zone);
	}

	if (! defined('URL')) {
		$url = new \shgysk8zer0\Core\URL;
		$url->path = join(
			'/',
			array_diff(
				explode(DIRECTORY_SEPARATOR, BASE),
				explode('/', $_SERVER['DOCUMENT_ROOT'])
			)
		);
		unset($url->query, $url->user, $url->pass, $url->fragment);
		define('URL', rtrim($url, '/') .'/');
		unset($url);
	}

	if (@is_object($settings->error_reporting)) {
		error_reporting(array_reduce(
			array_keys(array_filter(get_object_vars($settings->error_reporting))),
			function(&$level, $item)
			{
				$level |= constant($item);
				return $level;
			},
			0
		));
	} elseif (@is_string($settings->error_reporting)) {
		error_reporting(constant(strtoupper($settings->error_reporting)));
	} elseif (@is_int($settings->error_reporting)) {
		error_reporting($settings->error_reporting);
	}

	if (@is_callable($settings->error_handler)) {
		set_error_handler($settings->error_handler, error_reporting());
	}

	if (@is_callable($settings->exception_handler)) {
		set_exception_handler($settings->exception_handler);
	}

	if (@is_object($settings->header)) {
		foreach (get_object_vars($settings->header) as $name => $value) {
			header("{$name}: {$value}");
		}
	}

	if ($session) {
		\shgysk8zer0\Core\Session::load();
		nonce(50);						// Set a nonce of n random characters
	}
}

/**
 * Optimized resource loading using static variables and closures
 * Intended to minimize resource usage as well as limit scope
 * of variables from inluce()s
 *
 * Similar to include(), except that it shares limited resources
 * and does not load into the current scope for security reasons.
 *
 * @param mixed args
 * @return boolean
 * @example load(string | array[string | array[, ...]]*)
 */
function load()
{
	static $DB, $URL, $headers, $doc, $settings, $session, $login, $cookie, $timer, $path = null;

	if (first_run(__FUNCTION__)) {
		$DB       = \shgysk8zer0\Core\PDO::load('connect.json');
		$URL      = \shgysk8zer0\Core\URL::load(URL);
		$headers  = \shgysk8zer0\Core\Headers::load();
		$doc      = new \shgysk8zer0\Core\HTML_Doc;
		$settings = \shgysk8zer0\Core\Resources\Parser::parseFile('settings.json');
		$session  = \shgysk8zer0\Core\Session::load();
		$login    = \shgysk8zer0\Core\Login::load();
		$cookie   = \shgysk8zer0\Core\Cookies::load($URL->host);
		$timer    = \shgysk8zer0\Core\Timer::load();

		if (defined('THEME')) {
			$path = join(DIRECTORY_SEPARATOR, [defined('BASE') ? BASE : dirname(__DIR__), 'components', THEME]);
		} else {
			$path = join(DIRECTORY_SEPARATOR, [defined('BASE') ? BASE : dirname(__DIR__), 'components']);
		}
	}

	array_map(
		function($arg) use (
			$DB,
			$URL,
			$headers,
			$doc,
			$settings,
			$session,
			$login,
			$cookie,
			$timer,
			$path
		) {
			if (is_string($arg)) {
				require $path . DIRECTORY_SEPARATOR . $arg . '.php';
			} elseif (is_array($arg)) {
				call_user_func_array('load', $arg);
			}
		},
		func_get_args()
	);
}

/**
 * Similar to load(), except that it returns rather than prints
 *
 * @example(string | array[string | array[, ...]]*)
 * @param mixed (string, arrays, ... whatever. They'll be converted to an array)
 * @return string (results echoed from load())
 */
function load_results()
{
	ob_start();
	call_user_func_array('load', func_get_args());
	return ob_get_clean();
}

/**
 * strips leading trailing and closing tags, including leading
 * new lines, tabs, and any attributes in the tag itself.
 *
 * @param $html   html content to be stripping tags from
 * @return string html content with leading and trailing tags removed
 * @example strip_enclosing_tags('<div id="some_div" ...><p>Some Content</p></div>')
 * @deprecated
 */
function strip_enclosing_tag($html = null)
{
	return preg_replace('/^\n*\t*\<.+\>|\<\/.+\>$/', '', (string)$html);
}

/**
 * Converts an array into a string of HTML tags containing
 * the values of the array... useful for tables and lists.
 *
 * @param string $tag (Surrounding HTML tag)
 * @param array $content
 * @param array $attributes
 * @return string
 * @deprecated
 */
function html_join(
	$tag,
	array $content = null,
	array $attributes = null
)
{
	$tag = preg_replace('/[^a-z]/', null, strtolower((string)$tag));
	$attributes = array_to_attributes($attributes);
	return "<{$tag} {$attributes}>" . join("</{$tag}><{$tag}>", $content) . "</{$tag}>";
}

/**
 * Converts ['attr' => 'value'...] to attr="value"
 *
 * @param  array $attributes  [Key => value pairing of attributes]
 * @return string
 * @deprecated
 */
function array_to_attributes(array $attributes = null)
{
	if (is_null($attributes)) {
		return null;
	}

	$str = '';

	foreach ($attributes as $name => $value) {
		$str .= $name . '=' . htmlspecialchars($value);
	}

	return trim($str);
}

/**
 * Prints out information about $data
 * Wrapped in html comments or <pre><code>
 *
 * @param mixed $data[, boolean $comment]
 * @return void
 * @deprecated
 */
function debug($data = null, $comment = false)
{
	if (isset($comment)) {
		echo '<!--';
		print_r($data, is_ajax());
		echo '-->';
	} else {
		echo '<pre><code>';
		print_r($data, is_ajax());
		echo '</code></pre>';
	}
}

/**
 * Check login status, and optionally role
 *
 * @param  tring $role  user, admin, etc
 * @param  string $exit option for action if checks do not pass
 *
 * @return void
 */
function require_login($role = null, $exit = 'notify')
{
	$login = \shgysk8zer0\Core\Login::load();

	if (!$login->logged_in) {
		switch((string)$exit) {
			case 'notify':
				$resp = new \shgysk8zer0\Core\JSON_Response;
				$resp->notify(
					'We have a problem :(',
					'You must be logged in for that'
				);
				exit($resp);

			case '403':
			case 'exit':
				http_response_code(403);
				exit();

			case 'return' :
				return false;

			default:
				http_response_code(403);
				exit();
		}
	} elseif (is_string($role)) {
		$role = strtolower($role);
		$resp = new \shgysk8zer0\Core\JSON_Response();
		$roles = ['new', 'user', 'admin'];

		$user_level = array_search($login->role, $roles);
		$required_level = array_search($role, $roles);

		if (!$user_level or !$required_level) {
			$resp->notify(
				'We have a problem',
				'Either your user\'s role or the required role are invalid',
				'images/icons/info.png'
			);
			exit($resp);
		} elseif ($required_level > $user_level) {
			$resp->notify(
				'We have a problem :(',
				sprintf("You are logged in as %s but this action requires %s", $login->role, $role),
				'images/icons/info.png'
			);
			exit($resp);
		} else {
			return true;
		}
	} else {
		return true;
	}
}

/**
 * A nonce is a random string used for validation.
 * One is generated for every session, and is used to
 * prevent such things as brute force attacks on form submission.
 * Without checking a nonce, it becomes easier to brute force login attempts
 *
 * @param void
 * @return void
 */
function check_nonce()
{
	if (
		!(
			array_key_exists('nonce', $_POST) and
			array_key_exists('nonce', $_SESSION)
		)
		or $_POST['nonce'] !== $_SESSION['nonce']
	) {
		$resp = new \shgysk8zer0\Core\JSON_Response();
		$resp->notify(
			'Something went wrong :(',
			'Your session has expired. Try again',
			'images/icons/network-server.png'
		)->error(
			"nonce not set or does not match"
		)->sessionStorage(
			'nonce',
			nonce()
		)->attributes(
			'[name=nonce]',
			'value',
			$_SESSION['nonce']
		);
		exit($resp);
	};
}

/**
 * Content-Security-Policy is a set of rules given to a browser
 * via an HTTP header, providing a list of allowable resources.
 *
 * If a resources is requested that is not specifically allowed
 * in CSP, it is blocked. This prevents such things as key-loggers,
 * adware, and other forms of malware from having any effect.
 *
 * @see http://www.html5rocks.com/en/tutorials/security/content-security-policy/
 * @param void
 * @return void
 */
function CSP()
{
	$policy = \shgysk8zer0\Core\Resources\Parser::parseFile('settings.json');

	if (! is_object($policy) or ! isset($policy->csp)) {
		return;
	} else {
		$policy = $policy->csp;
	}

	if (isset($policy->enforce)) {
		$enforce = $policy->enforce;
		unset($policy->enforce);
	} else {
		$enforce = true;
	}

	$policy = get_object_vars($policy);

	$csp = array_reduce(
		array_keys($policy),
		function($carry, $item) use ($policy)
		{
			$src = $policy[$item];
			$carry .= (is_array($src)) ? $item . ' ' . join(' ', $src) . ';' : "{$item} {$src};";
			return $carry;
		},
		''
	);

	$csp = str_replace('%NONCE%', $_SESSION['nonce'], $csp);

	header(($enforce)
		? "Content-Security-Policy: {$csp}"
		: "Content-Security-Policy-Report-Only: {$csp}"
	);
}

/**
 * Checks to see if the server is also the client.
 *
 * @param void
 * @return boolean
 */
function localhost()
{
	return ($_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR']);
}

/**
 * Returns whether or not this is a secure (HTTPS) connection
 *
 * @param void
 * @return boolean
 */
function https()
{
	return array_key_exists('HTTPS', $_SERVER) and $_SERVER['HTTPS'];
}

/**
 * Checks and returns whether or not Do-Not-Track header
 * requests that we not track the client
 *
 * @param void
 * @return boolean
 */
function DNT()
{
	return array_key_exists('HTTP_DNT', $_SERVER) and $_SERVER['HTTP_DNT'];
}

/**
* Convert an address to GPS coordinates (longitude & latitude)
* using Google Maps API
*
* @param  string $Address Postal address
* @return stdClass        {"lat": $latitude, "lng": $longitude}
*/
function address_to_gps($Address = null)
{
	if (!is_string($Address)) {
		return false;
	}

	$request_url = "http://maps.googleapis.com/maps/api/geocode/xml?address=".urlencode($Address)."&sensor=true";
	$xml = simplexml_load_file($request_url);

	if (! empty($xml) and $xml->status == "OK") {
		return $xml->result->geometry->location;
	} else {
		return false;
	}
}

/**
 * Checks for the custom Request-Type header sent in my ajax requests
 *
 * @param void
 * @return boolean
 */
function is_ajax()
{
	return (
		array_key_exists('HTTP_REQUEST_TYPE', $_SERVER)
		and $_SERVER['HTTP_REQUEST_TYPE'] === 'AJAX'
	);
}

/**
 * Sets HTTP Content-Type header
 *
 * @param string $type
 * @return void
 * @deprecated
 */
function header_type($type = null)
{
	header('Content-Type: ' . $type . PHP_EOL);
}

/**
 * Defines a variety of things using the HTTP_USER_AGENT header,
 * such as operating system and browser
 *
 * @param void
 * @return void
 */
function define_UA()
{
	if (! defined('UA')){
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			define('UA', $_SERVER['HTTP_USER_AGENT']);
			if (preg_match("/Firefox/i", UA)) {
				define('BROWSER', 'Firefox');
			} elseif (preg_match("/Chrome/i", UA)) {
				define('BROWSER', 'Chrome');
			} elseif (preg_match("/(MSIE)|(TRIDENT)/i", UA)) {
				define('BROWSER', 'IE');
			} elseif (preg_match("/(Safari)||(AppleWebKit)/i", UA)) {
				define('BROWSER', 'Webkit');
			} elseif (preg_match("/Opera/i", UA)) {
				define('BROWSER', 'Opera');
			} else {
				define('BROWSER', 'Unknown');
			}

			if (preg_match("/Windows/i", UA)) {
				define('OS', 'Windows');
			} elseif (preg_match("/Ubuntu/i", UA)) {
				define('OS', 'Ubuntu');
			} elseif (preg_match("/Android/i", UA)) {
				define('OS', 'Android');
			} elseif (preg_match("/(IPhone)|(Macintosh)/i", UA)) {
				define('OS', 'Apple');
			} elseif (preg_match("/Linux/i", UA)) {
				define('OS', 'Linux');
			} else {
				define('OS', 'Unknown');
			}
		} else {
			define('BROWSER', 'Unknown');
			define('OS', 'Unknown');
		};
	}
}

/**
 * Generates a random string to be used for form validation
 *
 * @param integer $length
 * @return string
 */
function nonce($length = 50)
{
	$length = (int)$length;
	if (array_key_exists('nonce', $_SESSION)) {
		return $_SESSION['nonce'];
	}
	//We are going to shuffle an alpha-numeric string to get random characters
	$str = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);

	if (strlen($str) < $length) {
		$str .= nonce($length - strlen($str));
	}

	$_SESSION['nonce'] = $str;
	return $str;
}

/**
 * Checks whether or not the current request was sent
 * from the same domain
 *
 * @param void
 * @return boolean
 */
function same_origin()
{
	if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
		return parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) === $_SERVER['SERVER_NAME'];
	} elseif (array_key_exists('HTTP_REFERER', $_SERVER)) {
		return parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $_SERVER['SERVER_NAME'];
	} else {
		return false;
	}
}

/**
 * @param void
 * @return string (Directory one level below DOCUMENT_ROOT)
 * @deprecated
 */
function sub_root()
{
	return dirname($_SERVER['DOCUMENT_ROOT']);
}

/**
 * Remove from array by key and return it's value
 *
 * @param string $key, array $array
 * @return array | null
 */
function array_remove($key = null, array &$array)
{
	$key = (string)$key;
	if (array_key_exists($key, $array)) {
		$val = $array[$key];
		unset($array[$key]);
		return $val;
	} else {
		return null;
	}
}

/**
 * Checks if the array that is the product
 * of array_diff is empty or not.
 *
 * First, store all arguments as an array using
 * func_get_arg() as $keys.
 *
 * Then, pop off the last argument as $arr, which is assumed
 * to be the array to be searched and save it as its
 * own variable. This will also remove it from
 * the arguments array.
 *
 * Then, convert the array to its keys using $arr = array_keys($arr)
 *
 * Finally, compare the $keys by lopping through and checking if
 * each $key is in $arr using in_array($key, $arr)
 *
 * @param string[, string, .... string] array
 * @return boolean
 * @example array_keys_exist(
 *          	'red',
 *           	'green',
 *           	'blue',
 *            	[
 *          	  'red' => '#f00',
 *          	  'green' => '#0f0',
 *          	  'blue' => '#00f'
 *             	]
 *          ) // true
 */
function array_keys_exist()
{
	$keys = func_get_args();
	$arr = array_pop($keys);
	$arr = array_keys($arr);

	foreach ($keys as $key) {
		if (! in_array($key, $arr, true)) {
			return false;
		}
	}
	return true;
}

/**
 * Tests if each value in an array is true
 *
 * @param  array  $arr the array to test
 * @return bool        all array values are true
 */
function array_all_true(array $arr)
{
	$arr = array_unique($arr);
	return (count($arr) === 1 and $arr[0] === true);
}

/**
 * Convert a multi-dimensional array into a simple array
 *
 * Can't say that I'm entirely sure how it does what it does,
 * only that it works
 *
 * @param mixed args
 * @return array
 */
function flatten()
{
	return iterator_to_array(new \RecursiveIteratorIterator(
		new \RecursiveArrayIterator(func_get_args())), false);
}

/**
 * Prints out an unordered list from an array
 *
 * @param array $array
 * @return void
 * @deprecated
 */
function list_array(array $array)
{
	$list = "<ul>";
	foreach ($array as $key => $entry) {
		if (is_array($entry)) {
			$list .= list_array($value);
		} else {
			$entry = (string)$entry;
			$list .= "<li>{$key}: {$entry}</li>";
		}
	}
	$list .= "</ul>";

	return $list;
}

/**
 * Checks if an array is associative array
 * (A single index is a string)
 *
 * @param array $array
 * @return bool
 */
function is_assoc(array $array)
{
	return (bool)count(array_filter(array_keys($array), 'is_string'));
}

/**
 * Checks if an array is indexed(numerical)
 *
 * @param array $array
 * @return bool
 */
function is_indexed(array $array)
{
	return (bool)count(array_filter(array_keys($array), 'is_int'));
}

/**
 * Because I was tired of writing this... the ultimate point of programming, after all
 *
 * @param mixed $n
 * @return boolean
 * @deprecated
 */
function is_a_number($n = null)
{
	return is_numeric($n);
}

/**
 * Opposite of previous.
 *
 * @param mixed $n
 * @return boolean
 * @deprecated
 */
function is_not_a_number($n = null)
{
	return ! is_numeric($n);
}

/**
 * Checks if $str validates as an email
 *
 * @param string $str
 * @return bolean
 * @link http://php.net/manual/en/filter.filters.validate.php
 */
function is_email($str = null)
{
	return filter_var($str, FILTER_VALIDATE_EMAIL);
}

/**
 * Checks if $str validates as a URL
 *
 * @param string $str
 * @return bolean
 * @link http://php.net/manual/en/filter.filters.validate.php
 */
function is_url($str = null)
{
	return filter_var($str, FILTER_VALIDATE_URL);
}

/**
 * Checks $str againts the pattern for its type
 *
 * @param string $str
 * @return boolean
 */
function is_datetime($str)
{
	return pattern_check('datetime', $str);
}

/**
 * Checks $str againts the pattern for its type
 *
 * @param string $str
 * @return boolean
 */
function is_date($date)
{
	return pattern_check('date', $str);
}

/**
 * Checks $str againts the pattern for its type
 *
 * @param string $str
 * @return boolean
 */
function is_week($str)
{
	return pattern_check('week', $str);
}

/**
 * Checks $str againts the pattern for its type
 *
 * @param string $str
 * @return boolean
 */
function is_time($str)
{
	return pattern_check('time', $str);
}

/**
 * Checks $str againts the pattern for its type
 *
 * @param string $str
 * @return boolean
 */
function is_color($str)
{
	return pattern_check('color', $str);
}

/**
 * Checks $str againts the pattern $type
 *
 * @param string $str
 * @param string $type
 * @return boolean
 */
function pattern_check($type, $str)
{
	return preg_match('/^' . pattern($type) . '$/', $str);
}

/**
 * Checks that each $inputs is set and matches a pattern
 *
 * Loops through an array of inputs, checking that
 * it exists in $_REQUEST, and checks that $_REQUEST[$key]
 * matches the specified pattern.
 *
 * @param array $inputs ([$key => $test])
 * @param array $souce ($_POST, $_GET, $_REQUEST, [])
 * @return mixed (null if all inputs valid, selector '[name="$key"]' of first invalid input if not)
 * @example pattern_check(['num' => '\d', 'user' => is_email($source['user])], $source)
 */
function check_inputs(array $inputs = array(), array $source = null)
{
	if (! is_array($source)) {
		$source = $_REQUEST;
	}

	foreach ($inputs as $key => $test) {
		if (
			! array_key_exists($key, $source)
			or (is_bool($test) and !$test)
			or (is_string($test) and !preg_match('/^' . $test . '$/', $source[$key]))
		) {
			return "[name=\"{$key}\"]";
		}
	}
	return null;
}

/**
 * Useful for pattern attributes as well as server-side input validation
 * Must add regexp breakpoints for server-side use ['/^$pattern$/']
 *
 * @param string $type
 * @return string (regexp)
 */
function pattern($type = null, $class = "\\shgysk8zer0\\Core_API\\Abstracts\\RegExp")
{
	$type = strtoupper($type);
	if (defined("{$class}::{$type}")) {
		return constant("{$class}::{$type}");
	}
}

/**
 * Converts characters to UTF-8. Replaces special chars.
 *
 * @param string $string
 * @return (string UTF-8 converted)
 * @example utf('This & that') //Returns 'This &amp; that'
 */
function utf($string = null)
{
	return htmlentities($string, ENT_QUOTES | ENT_HTML5, "UTF-8");
}

/**
 * List files in given path. Optional extension and strip extension from results
 *
 * @param  string $path      Path to search
 * @param  string $ext       Optional extension to search for
 * @param  bool   $strip_ext Whether or not to trim off the extension
 * @return array             Array of matching files
 */
function ls($path = __DIR__, $ext = null, $strip_ext = false)
{
	$path = realpath($path);
	if ($path === false) {
		return $path;
	}

	$files = @scandir($path);
	if ($files === false) {
		return $path;
	}

	$files = array_filter(
		array_diff($files, array('.', '..')),
		function($file) use ($path)
		{
			return is_file($path . DIRECTORY_SEPARATOR . $file);
		}
	);

	if (is_string($ext)) {
		$ext = trim($ext, '.');
		$files = array_filter(
			$files,
			function($file) use ($ext)
			{
				return strtolower($ext) === strtolower(pathinfo($file, PATHINFO_EXTENSION));
			}
		);
	}
	if ($strip_ext === true) {
		$files = array_map('filename', $files);
		$files = array_filter($files, 'is_string');
	}
	return array_values($files);
}

/**
 * Base 64 encode $file. Does not set data: URI
 * @param string $file
 * @return string (base_64 encoded)
 */
function encode($file = null)
{
	if (file_exists($file)) {
		return base64_encode(file_get_contents($file));
	}
}

/**
 * Determine the mime-type of a file
 * using file info or file extension
 *
 * @param string $file
 * @return string (mime-type)
 * @example mime_type(path/to/file.txt) //Returns text/plain
 */
function mime_type($file = null)
{
	//Make an absolute path if given a relative path in $file

	$file = realpath($file);
	switch(str_replace('.', null, extension($file))) { //Start by matching file extensions
		case 'svg':
		case 'svgz':
			$type = 'image/svg+xml';
			break;

		case 'woff':
			$type = 'application/font-woff';
			break;

		case 'otf':
			$type = 'application/x-font-opentype';
			break;

		case 'sql':
			$type = 'text/x-sql';
			break;

		case 'appcache':
			$type = 'text/cache-manifest';
			break;

		case 'mml':
			$type = 'application/xhtml+xml';
			break;

		case 'ogv':
			$type = 'video/ogg';
			break;

		case 'webm':
			$type = 'video/webm';
			break;

		case 'php':
			$type = 'application/x-php';
			break;

		case 'ogg':
		case 'oga':
		case 'opus':
			$type = 'audio/ogg';
			break;

		case 'flac':
			$type = 'audio/flac';
			break;

		case 'm4a':
			$type = 'audio/mp4';
			break;

		case 'css':
		case 'cssz':
			$type = 'text/css';
			break;

		case 'js':
		case 'jsz':
			$type = 'text/javascript';
			break;

		default:		//If not found, try the file's default
			$finfo = new \finfo(FILEINFO_MIME);
			$type = preg_replace('/\;.*$/', null, (string)$finfo->file($file));
	}
	return $type;
}

/**
 * Reads the contents of a file ($file) and returns
 * the base64 encoded data-uri
 *
 * Useful for decreasing load times and storing resources client-side
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/data_URIs
 * @param strin $file
 * @return string (base64 encoded data-uri)
 */
function data_uri($file = null)
{
	$file = realpath($file);
	return 'data:' . mime_type($file) . ';base64,' . encode($file);
}

/**
 * Returns the extension for the specified file
 *
 * Does not depend on whether or not the file exists.
 * This function operates with the string, not the
 * filesystem
 *
 * @param string $file
 * @return string
 * @example extension('path/to/file.ext') //returns '.ext'
 */

function extension($file = null)
{
	return '.' . pathinfo($file, PATHINFO_EXTENSION);
}

/**
 * Returns the filename without path or extension
 * Does not depend on whether or not the file exists.
 * This function operates with the string, not the
 * filesystem
 *
 * @param string $file
 * @return string
 * @example filename('/path/to/file.ext') //returns 'file'
 */
function filename($file = null)
{
	return pathinfo($file, PATHINFO_FILENAME);
}

/**
* Concatonate an array of SVG files into a single SVG as <symbol>s
*
* If $output is given, the results will be saved to that file.
* Otherwise, the results will be returned as a string.
*
* @param array  $svgs   Array of SVG files
* @param string $output Optional name of output file
* @link http://css-tricks.com/svg-symbol-good-choice-icons/
*/
function SVG_symbols(array $svgs, $output = null)
{
	$dom = new \DOMDocument('1.0');
	$svg = $dom->appendChild(new \DOMElement('svg', null, 'http://www.w3.org/2000/svg'));

	array_reduce(
		$svgs,
		function(\DOMDocument $dom, $file)
		{
			$tmp = new \DOMDocument('1.0');
			$svg = file_get_contents($file);
			if (is_string($svg) and @file_exists($file)) {
				$svg = str_replace(["\r", "\n", "\t"], [], $svg);
				$svg = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/', null, $svg);
				$svg = preg_replace(['/^\<svg/', '/\<\/svg\>/'], ['<symbol', '</symbol>'], $svg);
				$tmp->loadXML($svg);
				$symbol_el = $tmp->getElementsByTagName('symbol')->item(0);
				$symbol_el->setAttribute('id', pathinfo($file, PATHINFO_FILENAME));
				if (
					!$symbol_el->hasAttribute('viewBox')
					and $symbol_el->hasAttribute('width')
					and $symbol_el->hasAttribute('height')
				) {
					$symbol_el->setAttribute(
						'viewBox',
						"0 0 {$symbol_el->getAttribute('width')} {$symbol_el->getAttribute('height')}"
					);
				}
				$symbol_el->setAttribute('width', '100%');
				$symbol_el->setAttribute('height', '100%');

				$symbol = $dom->importNode(
					$tmp->getElementsByTagName('symbol')->item(0),
					true
				);

				$dom->documentElement->appendChild($symbol);
			}
			return $dom;
		},
		$dom
	);

	$results = $dom->saveXML($dom->getElementsByTagName('svg')->item(0));
	if (is_string($output)) {
		file_put_contents($output, $results);
	} else {
		return $results;
	}
}

/**
 * Quick way to use an SVG <symbol>
 *
 * @param string  $icon        ID from the SVG source's symbols
 * @param array   $attributes  key => value set of attributes to set on SVG
 * @param string  $src         The link to the SVG file to use
 * @return string              HTML/SVG element containing a <use>
 *
 * @uses DOMDocument, DOMElement
 */
function SVG_use(
	$icon,
	array $attributes = array(),
	$src = 'images/icons/combined.svg'
)
{
	if (is_string($src) and ! is_url($src)) {
		$src = rtrim(URL, '/') . "/$src";
	}

	$svg = new \shgysk8zer0\Core\HTML_El('svg', null, 'http://www.w3.org/2000/svg', true);
	$use = new \shgysk8zer0\Core\HTML_El('use');
	$svg($use);
	$svg->{'@xmlns:xlink'} = 'http://www.w3.org/1999/xlink';
	$svg->{'@version'} = '1.1';
	$use->{'@xlink:href'} = "{$src}#{$icon}";

	array_map(
		function($attr, $val) use (&$svg)
		{
			$svg->{"@$attr"} = $val;
		},
		array_keys($attributes),
		array_values($attributes)
	);
	return "$svg";
}

/**
 * SVG_use(), but as a data-URI
 *
 * @param string  $icon        ID from the SVG source's symbols
 * @param array   $attributes  key => value set of attributes to set on SVG
 * @param string  $src         The link to the SVG file to use
 * @return string              URL encoded SVG
 *
 * @uses DOMDocument, DOMElement
 */
function SVG_use_URI(
	$icon,
	array $attributes = array(),
	$src = 'images/icons/combined.svg'
)
{
	return 'data:image/svg+xml;utf8,' . rawurlencode(SVG_use($icon, $attributes, $src));
}

/**
 * Trim a sentence to a specified number of words
 *
 * @param  string  $text      [original sentence]
 * @param  integer $max_words [maximum number of words to return]
 *
 * @return string             the first $max_words of $text
 */
function trim_words($text, $max_words = 0)
{
	$words = explode(' ', $text);
	if (count($words) > $max_words) {
		$text = join(' ', array_splice($words, 0, $max_words));
	}
	return $text;
}

/**
 * Download a file by settings headers and exiting with file content
 *
 * @param  string $file [local filname]
 * @param  string $name [name of file when downloaded]
 *
 * @return void
 */
function download($file = null, $name = null)
{
	if (isset($file) and file_exists($file)) {
		if (is_null($name)) {
			$name = basename($file);
		}
		http_response_code(200);
		header("Pragma: public");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header("Content-type: " . mime_type($file));
		header("Content-Disposition: attachment; filename=\"{$name}\"");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . filesize($file));
		readfile($file);
		exit();
	} else {
		http_response_code(404);
		exit();
	}
}

/**
 * Remove Leading and trailing single quotes
 *
 * @param string $str
 * @return string
 * @deprecated
 */
function unquote($str = null)
{
	return preg_replace("/^\'|\'$/", '', $str);
}

/**
 * Receives a string, returns same string with all words capitalized
 *
 * @param string $str
 * @return string
 * @deprecated
 */
function caps($str = null)
{
	return ucwords(strtolower($str));
}

/**
 * Finds the numeric average average of its arguments
 *
 * @param mixed args (All values should be numbers, int or float)
 * @return float (average)
 * @example average(1, 2) //Returns 1.5
 * @example average([1.5, 1.6]) //Returns 1.55
 */
function average()
{
	$args = flatten(func_get_args());
	return array_sum($args) / count($args);
}

/**
 * Is $n even?
 *
 * @param int $n
 * @return boolean
 */
function even($n)
{
	return ((int)$n % 2) === 0;
}

/**
 * Is $n odd?
 * Inverse of even()
 *
 * @param int $n
 * @return boolean
 */
function odd($n)
{
	return (is_int($n) and !even($n));
}

/**
 * Returns the sum of function's arguments
 *
 * @param numeric  [List of numbers]
 * @return numeric [sum]
 */
function sum()
{
	return array_sum(func_get_args());
}

/**
 * Returns $n squared
 *
 * @param  numeric $n [base number]
 * @return numeric     [$n squared]
 */
function sqr($n = 0)
{
	return (is_numeric($n)) ? pow($n, 2) : 0;
}

/**
 * Uses the pythagorean theorem to compute the magnitude of a
 * hypotenuse in n dimensions
 *
 * In any number of dimensions, the hypotenuse is the square root of
 * the sum of the squares of each dimension.
 *
 * @param numeric  [Uses func_get_args, so any number of numeric args]
 * @return numeric    [magnitude of hypotenuse]
 */
function magnitude()
{
	return sqrt(array_sum(array_map('sqr', func_get_args())));
}

/**
 * Alias of magnitude
 *
 * @param numeric $n  [Uses func_get_args, so any number of numeric args]
 * @return numeric    [magnitude of hypotenuse]
 */
function distance()
{
	return call_user_func_array('magnitude', func_get_args());
}

/**
 * Function to remove all tabs and newlines from source
 * Also strips out HTML comments but leaves conditional statements
 * such as <!--[if IE 6]>Conditional content<![endif]-->
 *
 * @param string $string (Pointer to string to minify)
 * @return string
 * @example minify("<!--Test-->\n<!--[if IE]>...<[endif]-->\n<p>...</p>") /Leaves only "<p>...</p>"
 * @deprecated
 */
function minify(&$string = null)
{
	$string = str_replace(["\r", "\n", "\t"], [], trim($string));

	return preg_replace(
		'/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/',
		null,
		$string
	);
}

/**
 * Converts date/time from one format to another
 *
 * @link http://php.net/manual/en/function.strtotime.php
 * @param mixed $from (Original time)
 * @param string $format
 * @param string $offset
 * @return string
 * @example convert_date('Now', 'r', '+2 weeks')
 * @deprecated
 */
function convert_date($from = null, $format = 'U', $offset = 'Now')
{
	if (is_string($from)) {
		$from = strtotime($from);
	} elseif (isset($from) and !is_int($from)) {
		$from = time();
	}

	if ($format === 'U') {
		return (int)date($format, strtotime($offset, $from));
	} else {
		return date($format, strtotime($offset, $from));
	}
}

/**
 * Computes the length in seconds of $length
 *
 * This can simply be computed by using strtotime
 * against the Unix Epoch (t = 0)
 *
 * @param string $time
 * @return int
 * @example get_time_offset('1 week'); //returns 604800
 * @example get_time_offset('1 week +1 second'); //returns 604801
 */
function get_time_offset($time)
{
	return strtotime('+' . $time, 0);
}

/**
 * Returns http content from request.
 *
 * @link http://www.php.net/manual/en/book.curl.php
 * @param string $request[, string $method]
 * @return string
 * @todo Handle both GET and POST methods
 * @deprecated
 */
function curl($request = null, $method = 'get')
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, (string)$request);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT,30);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

/**
 * See previous curl()
 *
 * @param string $url,
 * @param mixed $request
 * @return string
 * @deprecated
 */
function curl_post($url = null, $request = null)
{
	$requestBody = http_build_query($request);
	$connection = curl_init();
	curl_setopt($connection, CURLOPT_URL, (string)$url);
	curl_setopt($connection, CURLOPT_TIMEOUT, 30 );
	curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($connection, CURLOPT_POST, count($request));
	curl_setopt($connection, CURLOPT_POSTFIELDS, $requestBody);
	curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($connection, CURLOPT_FAILONERROR, 0);
	curl_setopt($connection, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($connection, CURLOPT_HTTP_VERSION, 1);		// HTTP version must be 1.0
	$response = curl_exec($connection);
	return $response;
}

/**
 * Converts ['path', 'to', 'something'] to '/path/to/something/'
 *
 * @param  array  $path_array Path components
 * @return string             Final path
 */
function array_to_path(array &$path_array)
{
	return DIRECTORY_SEPARATOR . trim(join(DIRECTORY_SEPARATOR, $path_array), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

/**
 * Build a path using a set of arguments
 *
 * @param string  Any number of directories using func_get_args()
 * @return string Final path
 */
function build_path()
{
	return array_to_path(func_get_args());
}

/**
 * Get required Apache & PHP modules from settings.ini,
 * compare against loaded modules, and return the difference
 *
 * @param void
 * @return mixed (null if all loaded, otherwise object of two arrays)
 * @example
 * $missing = module_test()
 * if (is_null($missing))...
 * else ...
 */
function module_test($settings)
{
	if (! function_exists('apache_get_modules')) {
		return;
	}
	/**
	 * First, check if the directives are set in settings.ini
	 * If not, return null
	 */

	if (
		!isset($settings->php_modules)
		or !isset($settings->apache_modules)
	) {
		return null;
	}

	$missing = new \stdClass();

	/**
	 * Missing PHP modules are the difference between an
	 * arrray of required modules and the array of loaded modules
	 */

	$missing->php = array_diff(
		$settings->php_modules,		//Convert the list in settings.ini to an array
		get_loaded_extensions()		//Get array of loaded PHP modules
	);
	$missing->apache = array_diff(
		$settings->apache_modules,	//Convert the list in settings.ini to an array
		apache_get_modules()							//Get array of loaded Apache modules
	);

	if (count($missing->php) or count($missing->apache)) {//If either is not empty, return $missing
		return $missing;
	} else {												//Otherwise return null
		return null;
	}
}

/**
 * Returns days of a week in an array (Mon - Fri | Sun - Sat)
 *
 * @param  bool $full           Mon-Fri only
 * @return array                Array of requested days
 */
function weekdays($full = true)
{
	if ($full) {
		return [
			'Sunday',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday'
		];
	}
	return [
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday'
	];
}
