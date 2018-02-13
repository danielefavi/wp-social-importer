<?php

class aioi_twitter {

	/**
	 * Store the twitter api key
	 *
	 * @var string
	 */
	protected $_key = null;

	/**
	 * Store the twitter api secret
	 *
	 * @var string
	 */
	protected $_secret = null;

	/**
	 * Store the twitter api token
	 *
	 * @var string
	 */
	protected $_token = null;

	/**
	 * Store the twitter token secret
	 *
	 * @var string
	 */
	protected $_token_secret = null;

	/**
	 * Store the twitter auth object for the api connection with the server.
	 *
	 * @var TwitterOAuth
	 */
	protected $_connection = null;

	/**
	 * Store the twitter user name.
	 *
	 * @var string
	 */
	protected $_screen_name = null;



	/**
	 * Initialize the local vars for a given social account.
	 *
	 * @param array $account
	 * @return void
	 */
	public function __construct($account) {
		$fields = array('key', 'secret', 'token', 'token_secret', 'screen_name');

		foreach($fields as $field) {
			if (!isset($account[$field])) return null;

			$loc_field_name = '_' . $field;
			$this->$loc_field_name = trim($account[$field]);
		}

		require_once('lib/twitteroauth/twitteroauth.php');
		$this->_connection = new TwitterOAuth($this->_key, $this->_secret, $this->_token, $this->_token_secret);
	}



	/**
	 * Test the if the social network settings are OK.
	 *
	 * @return Object
	 */
	public function connectionTest() {
		return $this->executeCall('account/verify_credentials', $parameters);
	}



	/**
	 * Retrieve the posts from twitter and pluck from it the relevant data.
	 *
	 * @param string $parameters
	 * @return array
	 */
	public function getPosts($parameters=null) {
		if (!empty($this->_screen_name)) {
			if (!is_array($parameters)) $parameters = array();
			$parameters['screen_name'] = $this->_screen_name;
		}

		if (isset($parameters['query'])) return $this->searchPosts($parameters);

		$result = $this->executeCall('statuses/user_timeline', $parameters);

		if (!$result['success']) return $result;

		$tweets = array();

		foreach($result['data'] as $tweet) {
			$new_tweet = $this->entityToPost($tweet);
			$tweets[] = $new_tweet;
		}

		$result['data'] = $tweets;

		return $result;
	}



	/**
	 * It make a search API call to the Twitter server.
	 *
	 * @param array $parameters
	 * @return array
	 */
	protected function searchPosts($parameters=null) {
		if (isset($parameters['query'])) $parameters['query'] = rawurlencode($parameters['query']);

		$result = $this->executeCall('search/tweets', $parameters);
		if (!$result['success'] or !isset($result['data']->statuses)) return $result;

		$tweets = array();

		foreach($result['data']->statuses as $tweet) {
			$new_tweet = $this->entityToPost($tweet);
			$tweets[] = $new_tweet;
		}

		$result['data'] = $tweets;

		return $result;
	}



	/**
	 * From a twitter feed pluck the relevant data that has to be saved in
	 * the wordpress post.
	 *
	 * @param Object $elem
	 * @return array
	 */
	protected function entityToPost($elem) {
		$new_tweet = array();

		$new_tweet['id'] = $elem->id_str;
		$new_tweet['created_at'] = (isset($elem->created_at)) ? date('Y-m-d H:i:s', strtotime($elem->created_at)) : date('Y-m-d H:i:s');
		$new_tweet['social_network'] = 'twitter';

		if (isset($elem->user->screen_name)) $new_tweet['original_url'] = 'https://twitter.com/' . $elem->user->screen_name . '/status/' . $new_tweet['id'];

		// Convert links to real links.
		if (isset($elem->text)) {

			if (isset($elem->retweeted_status) and isset($elem->retweeted_status->text)) {
				$rt_prefix = '';
				if (substr(strtoupper($elem->text), 0, 4) == 'RT @') {
					$ex = explode(':', $elem->text);
					$rt_prefix = $ex[0] . ': ';
				}
				$new_tweet['post'] = $rt_prefix . $elem->retweeted_status->text;
				$new_tweet['post'] = $this->translateLinks($new_tweet['post'], $elem->retweeted_status, true);
				$new_tweet['post'] = $this->translateLinks($new_tweet['post'], $elem);
			}
			else {
				$new_tweet['post'] = $elem->text;
				$new_tweet['post'] = $this->translateLinks($new_tweet['post'], $elem);
			}

			$new_tweet['title'] = strip_tags(html_entity_decode($new_tweet['post']));

		} // end if isset($elem->text)


		// Add Featured Image to Post
		if (isset($elem->entities->media[0]->media_url)) {
			$new_tweet['featured_image'] = $elem->entities->media[0]->media_url;
		}

		if (isset($elem->entities->urls[0]->expanded_url)) {
			$new_tweet['link_url'] = $elem->entities->urls[0]->expanded_url;
		}

		if (isset($new_tweet['link_url']) and empty($new_tweet['featured_image'])) {
			if (strpos(strtolower($new_tweet['link_url']), 'youtube.') !== false) {
				$new_tweet['featured_image'] = aioifeed_get_youtube_thumbnail($new_tweet['link_url']);
			}
			else if (strpos(strtolower($new_tweet['link_url']), 'vimeo.') !== false) {
				$new_tweet['featured_image'] = aioifeed_get_vimeo_preview($new_tweet['link_url']);
			}
		}

		return $new_tweet;
	}



	/**
	 * Transform URL and hashtags in actual HTML link. For instance
	 * #my-hashtah  -->  <a href="https://www.facebook.com/hashtag/my-hashtag">#my-hashtag</a>
	 *
	 * @param string $text
	 * @return string
	 */
	protected function translateLinks($feed_text, $elem=null, $retweet_check=false) {

		if (isset($elem->entities)) {

			// replacing the urls with actual url
			if (isset($elem->entities->urls) and is_array($elem->entities->urls)) {
				foreach($elem->entities->urls as $url_elem) {
					if ((strpos($feed_text, $url_elem->url) !== false) and (substr_count($feed_text, $url_elem->url) < 2)) {
						$linkhtml = '<a href="'.$url_elem->url.'" target="_blank">'.$url_elem->url.'</a>';
						$feed_text = str_replace($url_elem->url, $linkhtml, $feed_text);
					}
					else if (isset($url_elem->display_url) and (strpos($feed_text, $url_elem->display_url) !== false) and (substr_count($feed_text, $url_elem->display_url) < 2)) {
						$linkhtml = '<a href="'.$url_elem->display_url.'" target="_blank">'.$url_elem->display_url.'</a>';
						$feed_text = str_replace($url_elem->display_url, $linkhtml, $feed_text);
					}
				}
			} // end if urls

			if ($retweet_check) return $feed_text;

			// replacing the urls with actual url
			if (isset($elem->entities->media) and is_array($elem->entities->media)) {
				foreach($elem->entities->media as $media_elem) {
					if (isset($media_elem->url) and (strpos($feed_text, $media_elem->url) !== false)) {
						$linkhtml = '<a href="'.$media_elem->url.'" target="_blank">'.$media_elem->url.'</a>';
						$feed_text = str_replace($media_elem->url, $linkhtml, $feed_text);
					}
				}
			} // end if urls

			// translating the hashtag in link
			if (isset($elem->entities->hashtags) and is_array($elem->entities->hashtags)) {
				foreach($elem->entities->hashtags as $hash_elem) {
					if (strpos($feed_text, '#'.$hash_elem->text) !== false) {
						$hashlink = 'https://twitter.com/hashtag/' . $hash_elem->text . '?src=hash';
						$linkhtml = '<a href="'.$hashlink.'" target="_blank">#'.$hash_elem->text.'</a>';
						$feed_text = str_replace("#{$hash_elem->text}", $linkhtml, $feed_text);
					}
				}
			} // end if hashtags


			if ( isset($elem->entities->user_mentions) and is_array($elem->entities->user_mentions) ) {
				foreach($elem->entities->user_mentions as $user_mention) {
					$linkhtml = '<a href="https://twitter.com/@'.$user_mention->screen_name.'" target="_blank">@'.$user_mention->screen_name.'</a>';
					$feed_text = str_replace("@{$user_mention->screen_name}", $linkhtml, $feed_text);
				}
			}

		}

		return $feed_text;

	}



	/**
	 * From a list of error return the HTML.
	 *
	 * @param array $errors
	 * @return string
	 */
	protected function elaborateErrors($errors) {
		if (is_array($errors)) {
			$html_error = null;
			foreach($errors as $error) {
				if (isset($error->message)) {
					$html_error .= "<p>" . $error->message;
					if (isset($error->code)) $html_error .= "(twitter code: {$error->code})";
					$html_error .= "</p>";
				}
			}

			if ($html_error) return $html_error;
		}

		else if (is_string($errors)) {
			return '<p>' . ucfirst($errors) . '</p>';
		}

		return 'Errors occurred!';
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
	protected function executeCall($call_type, $parameters=null) {
		$return = array();
		$return['success'] = true;
		$return['data'] = null;

		if ($parameters) {
			$parameters = $this->translateParameters($parameters);
		}

		// default value
		$parameters['include_entities'] = true;
		$parameters['exclude_replies'] = true;

		$result = $this->_connection->get($call_type, $parameters);

		if ((isset($result->errors) and count($result->errors)) or isset($result->error)) {
			$errors = isset($result->errors) ? $result->errors : $result->error;

			$return['success'] = false;
			$return['error'] = $errors;
			unset($result->errors);

			$return['html_error'] = $this->elaborateErrors($errors);
		}

		if ($return['success']) $return['data'] = $result;

		return $return;
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
				'to_date'			=> 'until',
				'query'			=> 'q',
				'number_of_post'	=> 'count',
		);

		foreach($translations as $old_name => $new_name) {
			if (isset($parameters[$old_name])) {
				$parameters[$new_name] = $parameters[$old_name];
				unset($parameters[$old_name]);
			}
		}

		return $parameters;
	}


}
