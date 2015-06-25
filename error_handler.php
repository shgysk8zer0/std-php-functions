<?php
$errors   = new \shgysk8zer0\Core\Error_Event;
$reporter = \shgysk8zer0\Core\Errors::load();
$login    = \shgysk8zer0\Core\Login::load();

$errors($reporter);

array_map (
	function($lvl) use ($reporter, $errors)
	{
		$errors->$lvl = [$reporter, $reporter::LOG_METHOD];
	},
	[
		'error',
		'warning',
		'parse',
		'notice',
		'core_error',
		'core_warning',
		'compile_error',
		'compile_warning',
		'user_error',
		'user_warning',
		'user_notice'
	]
);
if (is_ajax() and $login->logged_in and $login->role === 'admin') {
	array_map(
		function($e_level) use ($reporter, $errors)
		{
			$errors->$e_level = [$reporter, $reporter::CONSOLE_METHOD];
		},
		$errors::errorConstants()
	);
}
