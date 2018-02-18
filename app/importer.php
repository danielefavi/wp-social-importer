<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class Importer {

	/**
	 * It contains the object of the importer (Facebook, Instagram or Twitter).
	 * This var is initialized by the constructor.
	 *
	 * @var object
	 */
	protected $_driver = null;

	/**
	 * Account to load.
	 *
	 * @var array
	 */
	protected $_account = null;

	/**
     * Allowed social network that this importer can support.
     *
     * @var array
     */
	public static $_allowed_socials = array('facebook', /*'linkedin',*/ 'twitter', 'instagram');



	/**
	 * Initialize the imported driver for a given account.
	 *
	 * @param array $account
	 * @return void
	 */
	public function __construct($account) {
		// checking if the social network is allowed
		if (isset($account['aioi_social_type']) and in_array($account['aioi_social_type'], self::$_allowed_socials)) {
			$classname = 'aioi_' . $account['aioi_social_type'];
			$this->_account = $account;
			$this->_account['buffer_option_key'] = $option_key = 'aioi_buffer_importer_account_' . $this->_account['aioi_slug'];

			// including the file with the class related to the account
			include_once($classname . '.php');
			// instancieting the importer object from $classname
			$this->_driver = new $classname($account);
		}
		else {
			return null;
		}
	}



	/**
	 * Test the connection. Use it to check if the account credentials
	 * and settings are right.
	 *
	 * @return array
	 */
	public function connectionTest() {
		return $this->_driver->connectionTest();
	}



	/**
	 * Get the post from the social network (depending on wich type of social
	 * network has been instantiated).
	 *
	 * @param mixed $parameters
	 * @return array
	 */
	public function getPosts($parameters=null) {
		$response = $this->_driver->getPosts($parameters);

		// buffering the response for the import so it will be faster on page refresh
		$to_save['time'] = time();
		$to_save['parameters'] = $parameters;
		$to_save['response'] = $response;
		update_option($this->_account['buffer_option_key'], $to_save);

		return $response;
	}



	/**
	 * Get the selected posts ($ids) from the social network through API
	 * and it saves them as wordpress post.
	 *
	 * @param array $ids
	 * @param mixed $parameters
	 * @param boolean $from_buffer
	 * @param integer $buffer_seconds
	 * @return string
	 */
	public function importPosts($ids, $parameters, $from_buffer=true, $buffer_seconds=600) {
		$created_wp_ids = array();

		if ($from_buffer) {
			$to_import_opt = get_option($this->_account['buffer_option_key']);

			$resp_secs_old = time() - $to_import_opt['time'];
			if ($resp_secs_old < $buffer_seconds) $to_import = $this->getPosts($parameters);
			else $to_import = $to_import_opt['response'];
		}
		else $to_import = $this->getPosts($parameters);

		if (isset($to_import['data']) and is_array($to_import['data'])) {
			foreach ($to_import['data'] as $sn_post) {
				// checking if the news is already imported
				if (! $this->newsIstoImport($ids, $sn_post)) continue;

				$created_wp_ids[] = $this->storePost($sn_post);
			} // end foreach $to_import
		} // end if $to_import['data'] is setted


		return $created_wp_ids;
	}



	/**
	 * From the data of the social news ($sn_post) it will be stored as
	 * a post.
	 *
	 * @param array $sn_post
	 * @return void
	 */
	protected function storePost($sn_post)
	{
		extract( $this->getPostImportSettings($sn_post['id']) );

		// saving the post
		$data = array(
			'post_content'		=> $sn_post['post'],
			'post_title'		=> $sn_post['title'],
			'post_status'		=> $aioi_post_status,
			'post_type'			=> $aioi_post_type,
			'post_author'		=> get_current_user_id(),
			'post_date'			=> $sn_post['created_at'],
			'comment_status'	=> $aioi_post_comments,
		);

		if ($aioi_post_type == 'post') {
			$data['post_category'] = $this->elaborateCategories($sn_post);
		}

		return $this->savePostDataAndMeta($data, $sn_post, $aioi_post_identifier);
	}



	/**
	 * Return the IDs of the category to associate to the post.
	 *
	 * @param array $sn_post
	 * @return array
	 */
	protected function elaborateCategories($sn_post)
	{
		$category_ids = array();

		if (!empty($this->_account['aioi_categories'])) {
			$ex = explode(',', $this->_account['aioi_categories']);

			foreach($ex as $cat_str) {
				$cat_str = strtolower(trim($cat_str));

				// get the category ID from the name; if the category does not
				// exists it will be created
				$cat_id = $this->aioifeed_get_create_cat( $cat_str );
				if ($cat_id) $category_ids[] = $cat_id;
			}
		}

		// getting (or creating) the category ID with the name of the social network
		$cat_id = $this->aioifeed_get_create_cat( $sn_post['social_network'] );
		if ($cat_id) $category_ids[] = $cat_id;

		return $category_ids;
	}



	/**
	 * Insert a new post from the data.
	 *
	 * @param array $data
	 * @param array $sn_post
	 * @param string $aioi_post_identifier
	 * @return void
	 */
	protected function savePostDataAndMeta($data, $sn_post, $aioi_post_identifier)
	{
		$wp_post_id = wp_insert_post($data);

		// saving metas
		update_post_meta($wp_post_id, '_aioi_post_identifier', $aioi_post_identifier);
		update_post_meta($wp_post_id, '_aioi_social_network', $sn_post['social_network']);

		if (isset($sn_post['original_url'])) {
			update_post_meta($wp_post_id, '_aioi_original_url', $sn_post['original_url']);
		}

		if (isset($sn_post['link_url'])) {
			update_post_meta($wp_post_id, '_aioi_link_url', $sn_post['link_url']);
		}

		if (isset($sn_post['featured_image']) and !empty($sn_post['featured_image'])) {
			$this->saveFeaturedImage($wp_post_id, $sn_post);
		}

		return $wp_post_id;
	}



	/**
	 * Check if the news is already imported.
	 *
	 * @param array $ids
	 * @param array $sn_post
	 * @return void
	 */
	protected function newsIstoImport($ids, $sn_post)
	{
		if (is_array($ids)) {
			if (!in_array($sn_post['id'], $ids)) return false;
		}
		else if (($ids != 'all') and ($sn_post['id'] != $ids)) return false;

		extract( $this->getPostImportSettings($sn_post['id']) );

		// checking if post has been already imported
		$post_check_args = array(
			'post_type' => $aioi_post_type,
			'meta_key' => '_aioi_post_identifier',
			'meta_value' => $aioi_post_identifier,
		);
		$post_check = get_posts($post_check_args);
		if ($post_check) return false; // post already imported Nothing

		return true;
	}



	/**
	 * Get the import settings set for the account.
	 * For instance if the news to import has to be DRAFT or PUBLISHED or
	 * the comments have to be OPEN or CLOSED
	 *
	 * @param numeric $social_post_id
	 * @return array
	 */
	protected function getPostImportSettings($social_post_id)
	{
		$pars = array();

		$pars['aioi_post_identifier'] = $this->_account['aioi_social_type'] . '_' . $social_post_id;
		$pars['aioi_post_type'] = !empty($this->_account['aioi_post_type']) ? $this->_account['aioi_post_type'] : 'post';
		$pars['aioi_post_status'] = !empty($this->_account['aioi_post_status']) ? $this->_account['aioi_post_status'] : 'draft';
		$pars['aioi_post_comments'] = !empty($this->_account['aioi_post_comments']) ? $this->_account['aioi_post_comments'] : 'closed';

		return $pars;
	}



	/**
	 * Get the image from the remote server, store it in the local server and
	 * set it as featured image.
	 *
	 * @param numeric $wp_post_id
	 * @param array $sn_post
	 * @return void
	 */
	protected function saveFeaturedImage($wp_post_id, $sn_post)
	{
		$upload_dir = wp_upload_dir(); // Set upload folder

		// getting the image url
		$url_image = $this->getImageRealUrl($sn_post['featured_image']);

		// trying to get the image fontent
		$image_data = false;
		try {
			$image_data = file_get_contents($url_image); // Get image data
		} catch (Exception $e) {
			$image_data = false;
		}

		if ($image_data) {
			$filename = $this->getFilename($url_image, $sn_post['social_network']);

			// Check folder permission and define file location
			if ( wp_mkdir_p( $upload_dir['path'] ) ) {
				$file = $upload_dir['path'] . '/' . $filename;
			}
			else {
				$file = $upload_dir['basedir'] . '/' . $filename;
			}

			// Create the image  file on the server
			file_put_contents( $file, $image_data );

			// Check image file type
			$wp_filetype = wp_check_filetype( $filename, null );

			// Set attachment data
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			// Create the attachment
			$attach_id = wp_insert_attachment( $attachment, $file, $wp_post_id );

			// Define attachment metadata
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

			// Assign metadata to attachment
			wp_update_attachment_metadata( $attach_id, $attach_data );

			// And finally assign featured image to post
			set_post_thumbnail( $wp_post_id, $attach_id );
		} // end if
	}



	/**
	 * Strip the filename from the URL given as parameter.
	 *
	 * @param string $url
	 * @param string $prefix
	 * @return string
	 */
	protected function getFilename($url, $prefix=null) {
		$filename = strtolower(basename($url));
		$final_ext = false;

		$exts = array('.jpg', '.jpeg', '.gif', '.png', '.tif', '.bmp');
		foreach($exts as $ext) {
			if (strpos($filename, $ext) !== false) {
				$final_ext = $ext;
				break;
			}
		}

		if (!$final_ext) {
			$ex = explode('.', $filename);
			if (isset($ex[1])) {
				$final_ext = substr($ex[1], 0, 3);
			}
		}

		if (!$final_ext) $final_ext = '.jpg';

		if (empty($prefix)) $prefix = rand(1,999999);

		$filename = $prefix .'_'. time() . '_' . rand(1,999999) . $final_ext;
		return $filename;
	}



	/**
	 * It checks if the $url is the direct link to the image or if a link
	 * that redirect to that image.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getImageRealUrl($url) {
		$url_lower = strtolower($url);
		$ex = explode('.', $url_lower);

		if (count($ex) > 1) {
			$final_part = $ex[(count($ex)-1)];
		}
		else $final_part = null;

		$img_ext = array('jpg', 'jpeg', 'gif', 'png', 'tif', 'bmp');

		if (!in_array($final_part, $img_ext)) {
			$url_red = $this->getRedirectUrl($url);
		}
		else $url_red = false;

		if ($url_red) return $url_red;

		return $url;
	}



	/**
	 * Sometimes the URL of an element is a redirect URL. For instance for an image
	 * you could have not the direct URL to that image, but an URL that will redirect
	 * you to that image.
	 * To get the final URL to the image this function fakes a browser request
	 * (since trying to access to an URL that redirects somewhere else does
	 * not give back anything).
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getRedirectUrl($url) {
		$redirect_url = null;

		$url_parts = @parse_url($url);
		if (!$url_parts) return false;
		if (!isset($url_parts['host'])) return false; //can't process relative URLs
		if (!isset($url_parts['path'])) $url_parts['path'] = '/';

		// Initiates a socket connection to the resource specified by hostname.
		$port = isset($url_parts['port']) ? (int)$url_parts['port'] : 80;
		$sock = fsockopen($url_parts['host'], $port, $errno, $errstr, 30);

		if (!$sock) return false;

		$request = "HEAD " . $url_parts['path'] . (isset($url_parts['query']) ? '?'.$url_parts['query'] : '') . " HTTP/1.1\r\n";
		$request .= 'Host: ' . $url_parts['host'] . "\r\n";
		$request .= "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.30729)\r\n";
		$request .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
		$request .= "Connection: Close\r\n\r\n";

		fwrite($sock, $request);
		$response = '';

		while(!feof($sock)) $response .= fread($sock, 8192);

		fclose($sock);

		if (preg_match('/^Location: (.+?)$/m', $response, $matches)) {
			if ( substr($matches[1], 0, 1) == "/" ) {
				return $url_parts['scheme'] . "://" . $url_parts['host'] . trim($matches[1]);
			}
			else {
				return trim($matches[1]);
			}
		}

		return false;
	}



	/**
	 * Return the ID of the category given as parameter.
	 * If the category does not exist it will be created.
	 *
	 * @param string $category_name
	 * @return numeric
	 */
	public function aioifeed_get_create_cat($category_name) {
		$term = term_exists($category_name, 'category');

		// the category exists
		if (is_array($term) and isset($term['term_id'])) {
			return $term['term_id'];
		}

		if (empty($term)) {
			$term_id = wp_create_category($category_name);
			if ($term_id) return $term_id;
		}

		return $term;
	}

}
