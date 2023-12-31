<?php

	namespace App\Libraries;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\{ DB, Cache, Session };
	use Aws\{ S3\S3Client, S3\Exception, Credentials\Credentials };
	use App\Models\{ Temp_Direct_Url };

	class Wasabi
	{
		static protected $ws3Client;


		/**
		* Init Amazon S3 client
		*
		* @return Object - S3Client
		**/
		private static function client()
		{
			exists_or_abort(config('filehosts.wasabi.enabled'), __('Wasabi is not enabled'));

			$credentials = new Credentials(config('filehosts.wasabi.access_key'), config('filehosts.wasabi.secret_key'));

			return new S3Client([
										'endpoint'    => "https://".config('filehosts.wasabi.bucket').".s3.".config('filehosts.wasabi.region').".wasabisys.com/",
										'bucket_endpoint' => true,
			              'version'     => config('filehosts.wasabi.version', 'latest'),
			              'region'      => config('filehosts.wasabi.region'),
			              'credentials' => $credentials,
			              'debug'       => app()->environment('development')
            			]);
		}



		/**
		* Upload an object to a given bucket
		*
		* @param String $file_key - file key (file name) in a bucket
		* @param String $tmp_file - path of the temporary uploaded file
		* @param String $bucket
		* @return String|Null - Object url or null
		**/
		public static function upload($file_key, $tmp_file, $bucket = null)
		{
			$ws3Client = Self::client();

			try
      {
        $result = $ws3Client->putObject([
          'Bucket' => $bucket ?? config('filehosts.wasabi.bucket'),
          'Key'    => $file_key,
          'Body'   => File::get($tmp_file),
          'ACL'    => 'bucket-owner-full-control'
        ]);

        return $result['ObjectURL'] ?? null;
      }
      catch (S3Exception $e)
      {
        if(app()->environment('development'))
        	abort(403, $e->getMessage());

        abort(404);
      }
		}


		/**
		* Check if object exists
		*
		* @param String $key
		* @param String $bucket
		* @param Array $options
		* @return boolean
		**/
		public static function object_exists(string $key, string $bucket = null, array $options = []): bool
		{
			if(!Self::$ws3Client) 
			{
				Self::$ws3Client = Self::client();
			}

			return Self::$ws3Client->doesObjectExist($bucket ?? config('filehosts.wasabi.bucket'), $key);
		}




		public static function download(array $config)
		{
			$key 				= $config['item_id'];
			$cache_id 	= $config['cache_id'];
			$expiry 		= $config['expiry'] ?? 86400;
			$bucket 		= $config['bucket'] ?? null;
			$options 		= $config['options'] ?? [];

			$temp_url = Temp_Direct_Url::where(['product_id' => $cache_id, "host" => "wasabi"])->where('expiry', '>=', time())->first();

			if(!$temp_url)
			{
				Self::$ws3Client = Self::client();
				
				if(!Self::object_exists($key, $bucket, $options))
				{
					exists_or_abort(null, __("File doesn't exist."));
				}

				$command = 	Self::$ws3Client->getCommand('GetObject', [
											'Bucket' => $bucket ?? config('filehosts.wasabi.bucket'),
									    'Key' 	 => $key
										]);

				$request = Self::$ws3Client->createPresignedRequest($command, time()+$expiry);

				$signed_url = (string) $request->getUri();

				DB::delete("DELETE FROM temp_direct_urls WHERE product_id = ?", [$cache_id]);

				$temp_url = Temp_Direct_Url::create([
					"product_id" => $cache_id,
					"host" => "wasabi",
					"url" => $signed_url,
					"expiry" => time()+$expiry,
				]);
			}

			return redirect()->away($temp_url->url);
		}




		public static function list_files(Request $request)
		{			
			$ws3Client = Self::client();

			try
      {
				$objects = 	$ws3Client->listObjects([
								      'Bucket' => $request->bucket ?? config('filehosts.wasabi.bucket'),
								      'MaxKeys' => $request->page_size ?? 20,
								      'Marker' => $request->marker,
								      'Delimiter' => '/',
								      'Prefix' => $request->keyword ?? ''
								    ]);

				$contents = $objects['Contents'] ?? [];
				$marker 	= end($contents)['Key'] ?? null;
				$has_more = $objects['IsTruncated'] ?? false;

				$files = [];

				foreach($objects['Contents'] ?? [] as $file)
				{					
					$files[] = [
						'name' 		 => $file['Key'],
						'id' 			 => $file['Key'],
						'mimeType' => strtolower(pathinfo($file['Key'], PATHINFO_EXTENSION)),
						'lastModified' => $file['LastModified'] ?? null,
						'size' => $file['Size'] ?? null
					];
				}

				return response()->json(['files_list' => compact('marker', 'files', 'has_more')]);
      }
      catch (S3Exception $e)
      {
        if(app()->environment('development'))
        	abort(403, $e->getMessage());

        abort(404);
      }
		}
		
		
		public static function test_connexion($request)
		{
		    $credentials = new Credentials($request->access_key, $request->secret_key);

				$ws3Client = new S3Client([
											'endpoint'    => "https://{$request->bucket}.s3.{$request->region}.wasabisys.com/",
											'bucket_endpoint' => true,
				              'version'     => $request->version ?? 'latest',
				              'region'      => $request->region,
				              'credentials' => $credentials,
				              'debug'       => app()->environment('development')
	            			]);

				return $ws3Client->doesBucketExist($request->bucket);
		}

		
	}