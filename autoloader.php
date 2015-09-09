<?php
set_include_path(getenv('CONFIG_DIR') . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'classes/shgysk8zer0/core/autoloader.php';
cli_init();
$autoloader = new \shgysk8zer0\Core\Autoloader(
	getenv('AUTOLOAD_FUNC'),
	getenv('AUTOLOAD_DIR'),
	explode(',', getenv('AUTOLOAD_EXTS'))
);

if (PHP_SAPI === 'cli') {
	assert_options(ASSERT_ACTIVE, true);
	assert_options(ASSERT_BAIL, true);
	assert_options(ASSERT_WARNING, false);
	assert_options(ASSERT_CALLBACK, function($script, $line, $message = null)
	{
		echo sprintf('Assert failed on %s:%u with message "%s"', $script, $line, $message);
	});
} else {
	assert_options(ASSERT_ACTIVE, false);
	assert_options(ASSERT_BAIL, false);
	assert_options(ASSERT_WARNING, false);
}
