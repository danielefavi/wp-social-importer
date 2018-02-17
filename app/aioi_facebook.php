<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class aioi_facebook {
	/**
	 * Store the facebook api key
	 *
	 * @var string
	 */
	protected $_key = null;

	/**
	 * Store the facebook api secret
	 *
	 * @var string
	 */
	protected $_secret = null;

	/**
	 * Store the facebook api token
	 *
	 * @var string
	 */
	protected $_token = null;

	/**
	 * Store the facebook page name (or page id)
	 *
	 * @var string
	 */
	protected $_page_id = null;

	/**
	 * Facebook API base URL
	 *
	 * @var string
	 */
	protected $_api_url = 'https://graph.facebook.com/v2.11/';



	/**
	 * Initialize the local vars (kery, secres, token, page_id) for a given
	 * social account.
	 *
	 * @param array $account
	 * @return void
	 */
	public function __construct($account)
	{
		$fields = array('key', 'secret', 'token', 'page_id');

		foreach($fields as $field) {
			$loc_field_name = '_' . $field;
			if (isset($account[$field])) $this->$loc_field_name = trim($account[$field]);
		}

		// $this->checkToken();

		if (empty($this->_page_id)) return null;
		if ((empty($this->_key) or empty($this->_secret)) and (empty($this->_token))) return null;
	}



	/**
	 * Retrieve the posts from facebook and pluck the relevant data.
	 *
	 * @param string $parameters
	 * @return array
	 */
	public function getPosts($parameters=null)
	{
		$result = $this->executeCall('post', $parameters);

		$posts = array();

		if (!empty($result['data']) and (count($result['data']) > 0)) {
			foreach($result['data'] as $fb_post) {
				$post = $this->entityToPost($fb_post);
				$posts[] = $post;
			}
		}

		$result['data'] = $posts;
		return $result;
	}



	/**
	 * From a facebook feed pluck the relevant data that has to be saved in
	 * the wordpress post.
	 *
	 * @param Object $elem
	 * @return array
	 */
	protected function entityToPost($elem)
	{
		$post = array();

		$post['id'] = $elem->id;
		$post['created_at'] = (isset($elem->created_time)) ? date('Y-m-d H:i:s', strtotime($elem->created_time)) : date('Y-m-d H:i:s');
		$post['social_network'] = 'facebook';

		$post['title'] = isset($elem->name) ? $elem->name : null;
		$post['post'] = isset($elem->message) ? $this->translateLinks($elem->message) : null;

		if (isset($elem->full_picture)) $post['featured_image'] = $elem->full_picture;
		if (isset($elem->link)) $post['link_url'] = $elem->link;

		$ex = explode('_', $post['id']);
		if (count($ex) > 1) {
			$post['original_url'] = 'http://www.facebook.com/'.$ex[0].'/posts/'.$ex[1];
		}
		else $post['original_url'] = null;

		if (isset($post['featured_image']) and (strpos(strtolower($post['featured_image']), 'external.xx.fbcdn.net/safe_image.php') !== false)) {
			$image_url = aioifeed_get_facebook_external_url_param($post['featured_image']);
			$post['featured_image'] = $image_url ? $image_url : $post['featured_image'];
		}

		return $post;
	}



	/**
	 * Transform URL and hashtags in actual HTML link. For instance
	 * #my-hashtah  -->  <a href="https://www.facebook.com/hashtag/my-hashtag">#my-hashtag</a>
	 *
	 * @param string $text
	 * @return string
	 */
	protected function translateLinks($text)
	{
		// adding http:// to URLs without it
		$text = str_replace(' www.', ' https://www.', $text);
		$text = str_replace("\nwww.", "\nhttps://www.", $text);

		// it translates a link in actual link
		$pattern = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
		preg_match($pattern, $text, $matches);
		if (isset($matches[0])) {
			$link = str_replace('"', '', $matches[0]);
			$fakelink = str_replace('#', '__hash_char_to_substitute__', $link);
			$link_html = '<a href="'.$fakelink.'" target="_blank">'.$fakelink.'</a>';
			$text = str_replace($link, $link_html, $text);
		}

		preg_match_all("/(#\w+)/", $text, $matches);
		if (isset($matches[1]) and count($matches[1])) {
			$hashtags = $matches[1];
			array_multisort(array_map('strlen', $hashtags),SORT_DESC, $hashtags);

			foreach($matches[1] as $hashtag) {
				$hashtag_text = str_replace('#', '', $hashtag);
				$hashlink = 'https://www.facebook.com/hashtag/' . $hashtag_text;
				$hash_replace = '<a target="_blank" href="'.$hashlink.'">#__hash_sec_remove__'.$hashtag_text.'__hash_sec_remove__</a>';
				$text = str_replace($hashtag, $hash_replace, $text);
			}
		}

		$text = str_replace('__hash_sec_remove__', '', $text);
		$text = str_replace('__hash_char_to_substitute__', '#', $text);

		return $text;
	}



	/**
	 * Returns the result of the API call to the facebook server.
	 * It returns an array where 'success' is the result of the call (boolean)
	 * and in 'data' you can find the result.
	 *
	 * @param string $call_type
	 * @param array $parameters
	 * @return string
	 */
	protected function executeCall($call_type='post', $parameters=null)
	{
		$return = array();
		$return['success'] = false;
		$return['data'] = null;

		if ($call_type = 'post') {

			if (!empty($this->_token)) {
				$resp = $this->sendHttpRequest( $this->getApiUrl($parameters, 'use_token') );
			}

			// wrong access token, try again with the tocken APP_ID|APP_SECRET
			if ((isset($resp->error) and ($resp->error->code == 190)) or (empty($this->_token))) {
				$resp = $this->sendHttpRequest( $this->getApiUrl($parameters) );
			}

			if (isset($resp->data) and !isset($resp->error)) {
				$return['success'] = true;
				$return['data'] = $resp->data;
				if (isset($resp->paging)) $return['paging'] = $resp->paging;
			}
			else if (isset($resp->error)) {
				$return['success'] = false;
				$return['error'] = $resp->error;

				if ($resp->error->code == 100) {
					$return['html_error'] = "It seems like you did not insert the PAGE ID. Please insert the PAGE ID and try again! <i>({$resp->error->message}</i>)";
				}
				else $return['html_error'] = $resp->error->message;
			}

		} // end if

		return $return;
	}


	/**
	 * Get the facebook api url to call.
	 * The parameter $mode is to indicate whether to use the token or apikey + apisecret.
	 *
	 * @param array $parameters
	 * @param string $mode
	 * @return string
	 */
	private function getApiUrl($parameters=null, $mode=null)
	{
		$fb_fields = urlencode('full_picture,picture,message,created_time,id,name,link');

		$url = $this->_api_url . $this->_page_id . '/posts?fields=' . $fb_fields;

		// adding parameters to url
		if (!empty($parameters) and is_array($parameters)) {
			$parameters = $this->translateParameters($parameters);

			foreach($parameters as $key => $val) {
				$conn = (strpos($url, '?') !== false) ? '&' : '?';
				$url .= $conn . $key .'='. urlencode(trim($val));
			}

		}

		if ($mode == 'use_token') {
			return $url . '&access_token=' . $this->_token;
		}

		return $url . '&access_token=' . $this->_key .'|'. $this->_secret;
	}



	/**
	 * Social networks have different parameters that means the same thing.
	 * For example if you want to set the number of post in Faceboo you have
	 * to use the parameter "limit" in Twitter "count".
	 *
	 * @param array $parameters
	 * @return array
	 */
	protected function translateParameters($parameters=array())
	{
		$translations = array(
			'number_of_post' => 'limit'
		);

		foreach($translations as $old_name => $new_name) {
			if (isset($parameters[$old_name])) {
				$parameters[$new_name] = $parameters[$old_name];
				unset($parameters[$old_name]);
			}
		}

		return $parameters;
	}



	/**
	 * Make the actual call to the server.
	 *
	 * @param string $url
	 * @param boolean $post
	 * @param array $post_pars
	 * @return string
	 */
	protected function sendHttpRequest($url, $post=false, $post_pars=array())
	{
		$resp = null;

		if ($post) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_pars, null, '&'));
			$resp = curl_exec($ch);
			curl_close($ch);
		}
		else {
			include_once('lib/Request.php');
			$req = new Request($url);
			$resp = $req->DownloadToString();
		}

		if ($resp) {
			return json_decode($resp);
		}

		return null;
	}


	function sendHttpRequest_backup_to_delete($url, $post=false, $post_pars=array())
	{
		$c = curl_init();
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

		if ($post)
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_pars, null, '&'));
		else
			curl_setopt($c, CURLOPT_URL, $url);

		$contents = curl_exec($c);
		$err  = curl_getinfo($c,CURLINFO_HTTP_CODE);
		curl_close($c);

		if ($contents) return $contents;
		else return $err;
	}



	/**
	 * Test the if the social network settings are OK.
	 *
	 * @return Object
	 */
	public function connectionTest()
	{
		return $this->executeCall('account/verify_credentials', $parameters);
	}

}
