<?php

class aioi_instagram {

	/**
	 * Store the instagram api Client ID
	 *
	 * @var string
	 */
	protected $_client_id = null;

	/**
	 * Store the instagram api client secret
	 *
	 * @var string
	 */
	protected $_client_secret = null;

	/**
	 * Store the instagram token
	 *
	 * @var string
	 */
	protected $_token = null;

	/**
	 * Store the instagram page id
	 *
	 * @var string
	 */
	protected $_page_id = null;

	/**
	 * Instagram api url
	 *
	 * @var string
	 */
	protected $_api_url = 'https://api.instagram.com/v1/';

	/**
	 * Instagram auth url
	 *
	 * @var string
	 */
	protected $_auth_url = 'https://instagram.com/oauth/authorize/';



	/**
	 * Initialize the local vars for a given social account.
	 *
	 * @param array $account
	 * @return void
	 */
	public function __construct($account) {
		include_once('lib/Instagram.php');

		$fields = array('client_id', 'client_secret', 'token', 'page_id');

		foreach($fields as $field) {
			if (isset($account[$field])) {
				$loc_field_name = '_' . $field;
				$this->$loc_field_name = trim($account[$field]);
			}
		}
	}



	/**
	 * Retrieve the posts from twitter and pluck from it the relevant data.
	 *
	 * @param string $parameters
	 * @return array
	 */
	public function getPosts($parameters=null) {
		$page_id = (!empty($this->_page_id)) ? $this->_page_id : 'self';

		$result = $this->executeCall('users/'.$page_id.'/media/recent', $parameters);

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
	 * From a twitter feed pluck the relevant data that has to be saved in
	 * the wordpress post.
	 *
	 * @param Object $elem
	 * @return array
	 */
	public function entityToPost($elem) {

		$post['id'] = $elem->id;
		$post['created_at'] = (isset($elem->caption->created_time)) ? date('Y-m-d H:i:s', $elem->caption->created_time) : date('Y-m-d H:i:s');
		$post['social_network'] = 'instagram';

		if (isset($elem->link)) $post['original_url']  = $elem->link;

		$post['post'] = isset($elem->caption->text) ? $this->translateLinks($elem->caption->text) : null;
		$post['title'] = isset($elem->caption->text) ? $elem->caption->text : null;

		if (isset($elem->images)) {
			if (isset($elem->images->standard_resolution)) $post['featured_image'] = $elem->images->standard_resolution->url;
			else if (isset($elem->images->low_resolution)) $post['featured_image'] = $elem->images->low_resolution->url;
			else if (isset($elem->images->thumbnail)) $post['featured_image'] = $elem->images->thumbnail->url;
			else {
				foreach ($elem->images as $image) {
					if (isset($image->url)) {
						$post['featured_image'] = $image->url;
						break;
					}
				}
			}
		}

		return $post;
	}



	/**
	 * Transform URLs and hashtags in actual HTML link within a text. For instance
	 * #my-hashtah  -->  <a href="https://www.facebook.com/hashtag/my-hashtag">#my-hashtag</a>
	 *
	 * @param string $text
	 * @return string
	 */
	protected function translateLinks($text) {
		// adding http:// to URLs without it
		$text = str_replace(' www.', ' http://www.', $text);
		$text = str_replace("\nwww.", "\nhttp://www.", $text);

		$pattern = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
		$replace = '<a href="${0}" target="_blank">${0}</a>';
		$text = preg_replace($pattern, $replace, $text);

		// Convert @ to follow
		preg_match_all('/(@([_a-z0-9\-]+))/i', $text, $matches_follow);
		if (isset($matches_follow[1]) and count($matches_follow[1])) {
			$follows = $matches_follow[1];
			array_multisort(array_map('strlen', $follows),SORT_DESC, $follows);

			foreach($follows as $follow) {
				$follow_text = str_replace('@', '', $follow);
				$followlink = 'https://www.instagram.com/'.$follow_text.'/';
				$follow_replace = '<a target="_blank" href="'.$followlink.'">@__hash_sec_remove__'.$follow_text.'__hash_sec_remove__</a>';
				$text = str_replace($follow, $follow_replace, $text);
			}
		}


		$hashtags = array();

		preg_match_all("/(#\w+)/", $text, $matches);
		if (isset($matches[1]) and count($matches[1])) $hashtags = $matches[1];

		if (!empty($hashtags)) {
			array_multisort(array_map('strlen', $hashtags),SORT_DESC, $hashtags);

			foreach($hashtags as $hashtag) {
				$hashtag_text = str_replace('#', '', $hashtag);
				$hashlink = 'https://www.instagram.com/explore/tags/' . $hashtag_text . '/';
				$hash_replace = '<a href="'.$hashlink.'" target="_blank">#__hash_sec_remove__'. $hashtag_text .'__hash_sec_remove__</a>';
				$text = str_replace($hashtag, $hash_replace, $text);
			}
		}

		$text = str_replace('__hash_sec_remove__', '', $text);

		return $text;
	}




	/**
	 * Getting the access token.
	 * If the connection test is successful means that the token is set and valid.
	 * If the connection test is not successful means the user has to be redirected
	 * the the instagram auth page in order to generate a new token.
	 *
	 * @return array
	 */
	public function getAccessToken() {
		if (!empty($this->_token)) {
			// testing if the current access token is still valid
			if ($this->connectionTest()) return array('token' => $this->_token);
		}

		$url = $this->getRedirectUri();

		return array('redirect_user' => $url);
	}



	/**
	 * Test the if the social network settings are OK.
	 *
	 * @return Object
	 */
	public function connectionTest($return_raw=false) {
		$parameters['access_token'] = $this->_token;
		$resp = $this->executeCall('users/self', $parameters);

		if ($return_raw) return $resp;

		return $resp['success'];
	}



	/**
	 * Get the url to the Instagram auth page.
	 *
	 * @param boolean $urlencoded
	 * @return string
	 */
	public function getRedirectUri($urlencoded=true) {
		$url = $this->_auth_url;
		$url .= '?client_id=' . $this->_client_id;
		$url .= '&redirect_uri=' . $this->getCallbackUrl();
		$url .= '&response_type=token';
		$url .= '&scope=public_content';
		//$url .= '&response_type=code';

		return $url;
	}



	/**
	 * Get the url back to the server after the user authorized the application.
	 *
	 * @param boolean $urlencoded
	 * @return string
	 */
	public function getCallbackUrl($urlencoded=true) {
		$redirect_url = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		$redirect_url .= (strpos($redirect_url, '?') !== false) ? '&' : '?';
		$redirect_url .= 'aioi_inst_at=1';

		if ($urlencoded) return urlencode($redirect_url);

		return $redirect_url;
	}



	/**
	 * Make the actual call to the server.
	 *
	 * @param string $url
	 * @param boolean $post
	 * @param array $post_pars
	 * @return string
	 */
	protected function sendHttpRequest($url, $post=false, $post_pars=array()) {
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



	/**
	 * Social networks have different parameters that means the same thing.
	 * For example if you want to set the number of post in Faceboo you have
	 * to use the parameter "limit" in Twitter "count".
	 *
	 * @param array $parameters
	 * @return array
	 */
	protected function translateParameters($parameters=array()) {
		$translations = array(
				 'number_of_post'	=> 'count'
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
	 * Returns the result of the API call.
	 * It returns an array where 'success' is the result of the call (boolean)
	 * and in 'data' you can find the result.
	 *
	 * @param string $call_type
	 * @param array $parameters
	 * @return string
	 */
	protected function executeCall($call_type, $parameters=array()) {
		$url = $this->_api_url . $call_type;
		$parameters['access_token'] = $this->_token;

		// if in the url there are no parameter and doesn't end with / areary
		if ((strpos($url, '?') === false) and (substr($url, 0, -1) != '/')) $url .= '/';

		if (is_array($parameters) and !empty($parameters)) {
			$parameters = $this->translateParameters($parameters);

			foreach($parameters as $key => $val) {
				$conn = (strpos($url, '?') !== false) ? '&' : '?';
				$url .= $conn . $key .'='. trim($val);
			}
		}

		$resp = $this->sendHttpRequest($url);

		return $this->elaborateResponse($resp);
	}



	/**
	 * From the result of the call to the instagram server it pluck the
	 * data. If there are errors this function will give you back the html errors
	 * as well.
	 *
	 * @param Object $resp
	 * @return array
	 */
	private function elaborateResponse($resp)
	{
		$return = array();
		$return['success'] = false;
		$return['data'] = null;

		if (isset($resp->meta->code) and ($resp->meta->code == 200)) $return['success'] = true;
		else {
			if (isset($resp->meta)) {
				$return['error'] = $resp->meta;

				if (isset($resp->meta->code) and ($resp->meta->code == 400)) {

					if (strtolower($resp->meta->error_message) == 'this user does not exist') {
						$return['html_error'] = "
							<p>
								This user does not exist. The <b>Page ID</b> seems wrong.
							</p>
						";
					}
					else {
						$token_url = $this->getRedirectUri();
						$return['html_error'] = '
							<p>
								The Access Token is missing or wrong. Click the button below to refresh the token.
								<br />If dosn\'t work please check you Instagram Client ID and CLient Secret.
								<br /><br />
								<a href="'.$token_url.'" class="button button-primary button-large">Get Instagram token</a>
							</p>
						';
					}
				}
				else if (isset($resp->meta->error_message)) $return['html_error'] = $resp->meta->error_message;

				//$return['html_error'] .= '<br /><br />' . $url;
			}
			else {
				$return['html_error'] = 'Generic error occurred: check if you Instagram account setting are right!
										 <br />
										 Check if you Instagram Page ID is correct or leave it blank to get your Instagram posts.';
			}
		}

		if (isset($resp->data)) $return['data'] = $resp->data;

		return $return;
	}


}
