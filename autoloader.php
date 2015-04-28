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
