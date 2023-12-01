<?php
	
		if(!function_exists('adj_ds'))
		{
			function adj_ds($str)
			{
				return str_ireplace(['/', '\\'], DIRECTORY_SEPARATOR, $str);
			}
		}

		define('LARAVEL_START', microtime(true));

		require_once adj_ds(__DIR__.'/../app/helpers.php');

		require_once adj_ds(__DIR__.'/../vendor/autoload.php');

		$app = require_once adj_ds(__DIR__.'/../bootstrap/app.php');

		$app->make('Illuminate\Contracts\Http\Kernel')->handle(Illuminate\Http\Request::capture());

		$db_username = config("database.connections.mysql.username");
		$db_dbname 	 = config("database.connections.mysql.database");	
		$db_password = config("database.connections.mysql.password");
		$db_host 		 = config("database.connections.mysql.host");

		if(env('DB_UPDATED_TO_v4') == '1')
		{
			dd("Database [$db_dbname] already updated, please export and import it to the new website with script v4");
		}

		$product_prices               = \App\Models\Product_Price::get()->groupBy('product_id');
	  $licenses                     = \App\Models\License::all();
	  $regular_licenses_to_replace  = [];
	  $extended_licenses_to_replace = [];

	  if($regular_license = $licenses->where('regular', 1)->sortBy('id')->first())
	  {
	    $other_regular_licenses = $licenses->where('regular', 1)->where('item_type', '!=', $regular_license['item_type']);
	    $regular_licenses       = array_column($licenses->where('regular', 1)->toArray(), "id");
	    $regular_license        = $regular_license ? $regular_license['id'] : null;  

	    $regular_licenses_to_replace = array_column($other_regular_licenses->toArray(), "id");
	  }

	  if($extended_license = $licenses->where('regular', 0)->sortBy('id')->first())
	  {
	    $other_extended_licenses = $licenses->where('regular', 0)->where('item_type', '!=', $extended_license['item_type']);
	    $extended_licenses       = array_column($licenses->where('regular', 0)->toArray(), "id");
	    $extended_license        = $extended_license ? $extended_license['id'] : null;

	    $extended_licenses_to_replace = array_column($other_extended_licenses->toArray(), "id");
	  }

	  $_product_prices = [];
	  
	  foreach($product_prices as $items)
	  {
	    $items = $items->toArray();

	    foreach($items as $item)
	    { 
	      if(isset($regular_licenses) && in_array($item['license_id'], $regular_licenses))
	      {
	        $item['license_id'] = $regular_license;
	      }
	      elseif(isset($extended_licenses) && in_array($item['license_id'], $extended_licenses))
	      {
	        $item['license_id'] = $extended_license;
	      }

	      $_product_prices[] = $item;
	    }
	  }

	  $_product_prices_2 = [];

	  foreach($_product_prices as $k => $_product_price)
	  {
	    $unique1 = array_intersect_key($_product_price, ['product_id' => null, 'license_id' => null]);

	    foreach($_product_prices_2 as $item)
	    {
	      $unique2 = array_intersect_key($item, ['product_id' => null, 'license_id' => null]);

	      if($unique1 === $unique2)
	      {
	        continue 2;
	      }
	    }

	    $_product_prices_2[] = $_product_price;
	  }

	  if(count($_product_prices_2))
	  {
	    $transactions = \App\Models\Transaction::all();

	    foreach($transactions as &$transaction)
	    {
	      if($licenses_ids = array_filter(explode(',', str_ireplace("'", '', $transaction->licenses_ids))))
	      {
	        $licenses_ids = filter_var_array($licenses_ids, FILTER_VALIDATE_INT);

	        foreach($licenses_ids as &$license_id)
	        {
	          if(isset($regular_licenses_to_replace) && in_array($license_id, $regular_licenses_to_replace))
	          {
	            $license_id = $regular_license;
	          }
	          elseif(isset($extended_licenses_to_replace) && in_array($license_id, $extended_licenses_to_replace))
	          {
	            $license_id = $extended_license;
	          }
	        }

	        $transaction->licenses_ids = implode(',', array_map("wrap_str", $licenses_ids));
	        $transaction->updated_at = now();
	        $transaction->save();
	      }
	    }

	    \DB::statement("TRUNCATE TABLE `product_price`");
	    \DB::statement("TRUNCATE TABLE `licenses`");
	    \DB::statement("ALTER TABLE `licenses` DROP INDEX `name_item_type`");
	    \DB::statement("ALTER TABLE `licenses` DROP COLUMN `item_type`");

	    $licenses = [
	      [
	        "id" => $regular_license,
	        "name" => "Regular license",
	        "regular" => 1
	      ]
	    ];

	    if(isset($extended_license))
	    {
	      $licenses[] = [
	        "id" => $extended_license,
	        "name" => "Extended license",
	        "regular" => 0
	      ];
	    }

	    \App\Models\License::insert($licenses);
	    \App\Models\Product_Price::insert($_product_prices_2);
	  }

	  $db_update_sql = <<<SQL
			START TRANSACTION;

			SET FOREIGN_KEY_CHECKS=0;

			DROP TABLE IF EXISTS `skrill_transactions`;

			RENAME TABLE subscriptions TO pricing_table;
			ALTER TABLE `pricing_table` ADD COLUMN `categories` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `products`;
			ALTER TABLE `pricing_table` ADD COLUMN `specifications` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `description`;
			ALTER TABLE `pricing_table` ADD COLUMN `popular` tinyint(1) NULL DEFAULT 0 AFTER `position`;
			ALTER TABLE `pricing_table` MODIFY COLUMN `products` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `slug`;
			ALTER TABLE `pricing_table` MODIFY COLUMN `limit_downloads_same_item` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT '0' AFTER `limit_downloads_per_day`;
			ALTER TABLE `pricing_table` MODIFY COLUMN `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP AFTER `popular`;
			ALTER TABLE `pricing_table` MODIFY COLUMN `updated_at` datetime NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`;
			ALTER TABLE `pricing_table` MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL AFTER `updated_at`;

			CREATE TABLE IF NOT EXISTS `cache`  (
			  `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `value` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `expiration` int NOT NULL,
			  PRIMARY KEY (`key`) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `cache_locks`  (
			  `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `owner` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `expiration` int NOT NULL,
			  PRIMARY KEY (`key`) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `custom_routes`  (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `slug` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
			  `view` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
			  `method` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT 'get',
			  `csrf_protection` tinyint(1) NULL DEFAULT 0,
			  `views` bigint NULL DEFAULT 0,
			  `active` tinyint(1) NULL DEFAULT 1,
			  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `updated_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `deleted_at` datetime NULL DEFAULT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  INDEX `name`(`name` ASC) USING BTREE,
			  INDEX `method`(`method` ASC) USING BTREE,
			  INDEX `updated_at`(`updated_at` ASC) USING BTREE,
			  INDEX `created_at`(`created_at` ASC) USING BTREE,
			  INDEX `slug`(`slug` ASC) USING BTREE,
			  INDEX `active`(`active` ASC) USING BTREE,
			  INDEX `views`(`views` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `prepaid_credits`  (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `amount` float NOT NULL DEFAULT 0,
			  `specs` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `popular` tinyint(1) NULL DEFAULT 0,
			  `order` tinyint NULL DEFAULT 0,
			  `discount` float NULL DEFAULT 0,
			  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `updated_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `deleted_at` datetime NULL DEFAULT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  INDEX `name`(`name` ASC) USING BTREE,
			  INDEX `popular`(`popular` ASC) USING BTREE,
			  INDEX `created_at`(`created_at` ASC) USING BTREE,
			  INDEX `updated_at`(`updated_at` ASC) USING BTREE,
			  INDEX `amount`(`amount` ASC) USING BTREE,
			  INDEX `order`(`order` ASC) USING BTREE,
			  INDEX `discount`(`discount` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `sessions`  (
			  `id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `user_id` bigint UNSIGNED NULL DEFAULT NULL,
			  `ip_address` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
			  `user_agent` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `payload` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `last_activity` int NOT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  INDEX `sessions_user_id_index`(`user_id` ASC) USING BTREE,
			  INDEX `sessions_last_activity_index`(`last_activity` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `statistics`  (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `traffic` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `browsers` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `devices` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `oss` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `date` date NULL DEFAULT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  UNIQUE INDEX `date`(`date` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `temp_direct_urls`  (
			  `product_id` bigint NULL DEFAULT NULL,
			  `host` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
			  `url` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `expiry` int NULL DEFAULT NULL,
			  UNIQUE INDEX `product_id`(`product_id` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `transaction_note`  (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `transaction_id` bigint NULL DEFAULT NULL,
			  `user_id` bigint NULL DEFAULT NULL,
			  `content` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
			  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `updated_at` datetime NULL DEFAULT CURRENT_TIMESTAMP,
			  `deleted_at` datetime NULL DEFAULT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  INDEX `transaction_id`(`transaction_id` ASC) USING BTREE,
			  INDEX `user_id`(`user_id` ASC) USING BTREE,
			  INDEX `created_at`(`created_at` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			CREATE TABLE IF NOT EXISTS `user_prepaid_credits`  (
			  `id` bigint NOT NULL AUTO_INCREMENT,
			  `prepaid_credits_id` bigint NOT NULL,
			  `transaction_id` bigint NOT NULL,
			  `user_id` bigint NOT NULL,
			  `credits` float NOT NULL DEFAULT 0,
			  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  `deleted_at` datetime NULL DEFAULT NULL,
			  PRIMARY KEY (`id`) USING BTREE,
			  INDEX `transaction_id`(`transaction_id` ASC) USING BTREE,
			  INDEX `prepaid_credits_id`(`prepaid_credits_id` ASC) USING BTREE,
			  INDEX `created_at`(`created_at` ASC) USING BTREE,
			  INDEX `updated_at`(`updated_at` ASC) USING BTREE,
			  INDEX `user_id`(`user_id` ASC) USING BTREE
			) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

			ALTER TABLE `affiliate_earnings` ADD COLUMN `amount` float NULL DEFAULT 0 AFTER `paid`;
			ALTER TABLE `affiliate_earnings` MODIFY COLUMN `paid` tinyint(1) NOT NULL DEFAULT 0 AFTER `commission_value`;
			ALTER TABLE `categories` ADD COLUMN `icon` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `slug`;
			ALTER TABLE `categories` ADD COLUMN `featured` tinyint(1) NULL DEFAULT 0 AFTER `for`;
			ALTER TABLE `categories` MODIFY COLUMN `for` tinyint(1) NULL DEFAULT 1 COMMENT '0 for posts / 1 for products' AFTER `range`;
			ALTER TABLE `categories` ADD INDEX `featured`(`featured` ASC) USING BTREE;
			ALTER TABLE `product_price` MODIFY COLUMN `promo_price` float NULL DEFAULT NULL AFTER `price`;
			ALTER TABLE `products` DROP INDEX `slug`;
			ALTER TABLE `products` DROP INDEX `name`;
			ALTER TABLE `products` DROP INDEX `is_dir`;
			ALTER TABLE `products` DROP INDEX `type`;
			ALTER TABLE `products` DROP INDEX `description`;
			ALTER TABLE `products` ADD COLUMN `file_extension` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `file_host`;
			ALTER TABLE `products` ADD COLUMN `preview_extension` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `preview_type`;
			ALTER TABLE `products` ADD COLUMN `fake_sales` bigint NULL DEFAULT NULL AFTER `minimum_price`;
			ALTER TABLE `products` ADD COLUMN `fake_comments` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `fake_sales`;
			ALTER TABLE `products` ADD COLUMN `fake_reviews` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `fake_comments`;
			ALTER TABLE `products` ADD COLUMN `affiliate_link` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `fake_reviews`;
			ALTER TABLE `products` ADD COLUMN `permalink` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `affiliate_link`;
			ALTER TABLE `products` ADD COLUMN `tmp_direct_link` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `permalink`;
			ALTER TABLE `products` MODIFY COLUMN `overview` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `short_description`;
			ALTER TABLE `products` MODIFY COLUMN `preview` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `file_name`;
			ALTER TABLE `products` MODIFY COLUMN `enable_license` tinyint(1) NULL DEFAULT NULL AFTER `hidden_content`;
			ALTER TABLE `products` MODIFY COLUMN `preview_url` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `authors`;
			ALTER TABLE `products` MODIFY COLUMN `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP AFTER `tmp_direct_link`;
			ALTER TABLE `products` MODIFY COLUMN `updated_at` datetime NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`;
			ALTER TABLE `products` MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL AFTER `updated_at`;
			ALTER TABLE `products` ADD UNIQUE INDEX `permalink`(`permalink` ASC) USING BTREE;
			ALTER TABLE `products` ADD INDEX `fake_sales`(`fake_sales` ASC) USING BTREE;
			ALTER TABLE `products` DROP COLUMN `is_dir`;
			ALTER TABLE `products` DROP COLUMN `type`;
			ALTER TABLE `reactions` DROP INDEX `item_type_item_id_user_id`;
			ALTER TABLE `reactions` DROP COLUMN `item_type`;
			ALTER TABLE `reactions` ADD UNIQUE INDEX `item_id_user_id`(`item_id`, `user_id`);
			ALTER TABLE `settings` ADD COLUMN `maintenance` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `affiliate`;
			ALTER TABLE `settings` MODIFY COLUMN `database` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `captcha`;
			ALTER TABLE `settings` MODIFY COLUMN `affiliate` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `database`;
			ALTER TABLE `settings` MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `maintenance`;
			ALTER TABLE `settings` MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`;
			ALTER TABLE `settings` MODIFY COLUMN `deleted_at` datetime NULL DEFAULT NULL AFTER `updated_at`;
			ALTER TABLE `subscription_same_item_downloads` ENGINE = InnoDB, ROW_FORMAT = Dynamic;
			ALTER TABLE `subscription_same_item_downloads` MODIFY COLUMN `subscription_id` bigint NOT NULL FIRST;
			ALTER TABLE `subscription_same_item_downloads` MODIFY COLUMN `product_id` bigint NOT NULL AFTER `subscription_id`;
			ALTER TABLE `subscription_same_item_downloads` MODIFY COLUMN `downloads` bigint NOT NULL AFTER `product_id`;
			ALTER TABLE `transactions` ADD COLUMN `guest_email` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `guest_token`;
			ALTER TABLE `transactions` ADD COLUMN `type` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `referrer_id`;
			ALTER TABLE `transactions` ADD COLUMN `sandbox` tinyint(1) NULL DEFAULT NULL AFTER `type`;
			ALTER TABLE `transactions` MODIFY COLUMN `licenses_ids` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `products_ids`;
			ALTER TABLE `transactions` DROP COLUMN `cs_token`;
			ALTER TABLE `transactions` ADD INDEX `sandbox`(`sandbox` ASC) USING BTREE;
			ALTER TABLE `user_subscription` DROP COLUMN `same_items_downloads`;
			ALTER TABLE `users` ADD COLUMN `prepaid_credits` float NULL DEFAULT NULL AFTER `bank_account`;
			ALTER TABLE `users` ADD COLUMN `affiliate_credits` float NULL DEFAULT NULL AFTER `prepaid_credits`;
			ALTER TABLE `users` ADD COLUMN `credits_sources` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER `affiliate_credits`;
			ALTER TABLE `users` MODIFY COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `credits_sources`;
			ALTER TABLE `users` MODIFY COLUMN `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`;
			ALTER TABLE `users` MODIFY COLUMN `deleted_at` timestamp NULL DEFAULT NULL AFTER `updated_at`;
			ALTER TABLE `users` ADD INDEX `credits`(`prepaid_credits` ASC) USING BTREE;
			ALTER TABLE `users` ADD INDEX `affiliate_credits`(`affiliate_credits` ASC) USING BTREE;

			SET FOREIGN_KEY_CHECKS=1;

			COMMIT;
SQL;

		\DB::unprepared($db_update_sql);

		update_env_var("DB_UPDATED_TO_v4", '1');
		
		if(function_exists("exec"))
		{
			$db_backup_file = "DATABASE_BACKUP.sql";

			exec('mysqldump -u'.$db_username.' -p'.$db_password.' --insert-ignore --complete-insert --skip-add-locks --no-create-info --no-create-db --skip-comments '.$db_dbname.' > '.$db_backup_file, $result, $result_code);

			if($result_code === 0)
			{
				$db_backup = file_get_contents($db_backup_file);
				$db_backup = 'START TRANSACTION;' . PHP_EOL . $db_backup . PHP_EOL . 'COMMIT;';
				$db_backup = str_replace("INSERT INTO", "INSERT IGNORE INTO", $db_backup);

				file_put_contents($db_backup_file, $db_backup);

				dd("The updated database has been exported to {$db_backup_file}");
			}
		}
		else
		{
			dd("PHP function exec() is disabled on your server, please try exporting '$db_dbname' database using phpMyAdmin or other tool.");
		}