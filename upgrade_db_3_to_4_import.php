<?php
	
		if(!function_exists('adj_ds'))
		{
			function adj_ds($str)
			{
				return str_ireplace(['/', '\\'], DIRECTORY_SEPARATOR, $str);
			}
		}

		define('LARAVEL_START', microtime(true));

		require_once adj_ds(__DIR__.'/../app/Helpers/Helpers.php');

		require_once adj_ds(__DIR__.'/../vendor/autoload.php');

		$app = require_once adj_ds(__DIR__.'/../bootstrap/app.php');

		$app->make('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::capture());

		if(env("DATABASE_V3_IMPORTED") == '1')
		{
			dd("Database already imported");
		}

		if(file_exists(base_path("DATABASE_BACKUP.sql")))
		{
			\DB::unprepared(file_get_contents(base_path("DATABASE_BACKUP.sql")));

			update_env_var("DATABASE_V3_IMPORTED", '1');

			dd("Database imported");
		}
		else
		{
			dd("Please upload DATABASE_BACKUP.sql file to ".base_path());
		}
		