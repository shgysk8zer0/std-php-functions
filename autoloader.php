<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';
cli_init();

set_include_path(join(PATH_SEPARATOR, [
	realpath(getenv('AUTOLOAD_DIR')),
	realpath(getenv('CONFIG_DIR')),
	get_include_path()
]));

spl_autoload_extensions(getenv('AUTOLOAD_EXTS'));
spl_autoload_register(getenv('AUTOLOAD_FUNC'));

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

