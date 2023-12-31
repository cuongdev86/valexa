<?php

	namespace App\Libraries;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\{ DB, Cache, Session };
	use Aws\{ S3\S3Client, S3\Exception, Credentials\Credentials };
	use App\Models\{ Temp_Direct_Url };

	class AmazonS3
	{
		static protected $s3;


		/**
		* Init Amazon S3 client
		*
		* @return Object - S3Client
		**/
		private static function client()
		{
			exists_or_abort(config('filehosts.amazon_s3.enabled'), __('Amazon S3 is not enabled'));

			$credentials = new Credentials(config('filehosts.amazon_s3.access_key_id'), config('filehosts.amazon_s3.secret_key'));

			return 	new S3Client([
			              'version'     => config('filehosts.amazon_s3.version', 'latest'),
			              'region'      => config('filehosts.amazon_s3.region'),
			              'credentials' => $credentials,
			              'debug'       => false
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
			$s3 = Self::client();

			try
      {
        $result = $s3->putObject([
          'Bucket' => $bucket ?? config('filehosts.amazon_s3.bucket'),
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
			if(!Self::$s3) 
			{
				Self::$s3 = Self::client();
			}

			return Self::$s3->doesObjectExist($bucket ?? config('filehosts.amazon_s3.bucket'), $key);
		}




		public static function download(array $config)
		{
			$key				= $config['item_id'];
			$cache_id 	= $config['cache_id'];
			$expiry 		= $config['expiry'] ?? 86400;
			$bucket 		= $config['bucket'] ?? null;
			$options 		= $config['options'] ?? [];

			$temp_url = Temp_Direct_Url::where(['product_id' => $cache_id, "host" => "amazon_s3"])->where('expiry', '>=', time())->first();

			if(!$temp_url)
			{
				Self::$s3 = Self::client();
				
				if(!Self::object_exists($key, $bucket, $options))
				{
					exists_or_abort(null, __("File doesn't exist."));
				}

				$command = 	Self::$s3->getCommand('GetObject', [
											'Bucket' => $bucket ?? config('filehosts.amazon_s3.bucket'),
									    'Key' 	 => $key
										]);

				$request = Self::$s3->createPresignedRequest($command, time()+$expiry);

				$signed_url = (string) $request->getUri();
				
				DB::delete("DELETE FROM temp_direct_urls WHERE product_id = ?", [$cache_id]);

				$temp_url = Temp_Direct_Url::create([
					"product_id" => $cache_id,
					"host" => "amazon_s3",
					"url" => $signed_url,
					"expiry" => time()+$expiry,
				]);
			}

			return redirect()->away($temp_url->url);
		}




		public static function list_files(Request $request)
		{			
			$s3 = Self::client();

			try
      {
				$objects = 	$s3->listObjects([
								      'Bucket' => $request->bucket ?? config('filehosts.amazon_s3.bucket'),
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
		    $credentials = new Credentials($request->access_key_id, $request->secret_key);

				$s3Client = new S3Client([
				              'version'     => $request->version ?? 'latest',
				              'region'      => $request->region,
				              'credentials' => $credentials,
				              'debug'       => app()->environment('development')
	            			]);
            			
				return $s3Client->doesBucketExist($request->bucket);
		}

		
	}