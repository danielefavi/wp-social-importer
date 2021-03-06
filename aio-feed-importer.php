<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/*
    Plugin Name: WP Social Importer
    Plugin URI: https://www.danielefavi.com/wp-social-importer/
    Description: Import news from Facebook, Twitter and Instagram
    Author: Daniele Favi
    Author URI: https://www.danielefavi.com/
    Version: 1.0.0
    Text Domain: aio-wp-social-importer
*/


// for testing
// ini_set('display_errors', 'On');
// error_reporting(E_ALL | E_STRICT);


// creating the menu entry on the wordpress administration panel
add_action('admin_menu', 'aioifeed_importer_create_menu_entry');



/**
 * Create the entries in the administration menu.
 *
 * @return void
 */
function aioifeed_importer_create_menu_entry() {
	$icon = plugins_url('images/arrow-87-16.png',__FILE__);

    add_menu_page('Social Importer', 'Social Importer', 'edit_posts', 'accounts-aio-importer', 'aioifeed_importer_fnc__accounts', $icon);

	add_submenu_page( null,
					 'Edit account',
					 'Edit account',
					 'edit_posts',
					 'accounts-edit-aio-importer',
					 'aioifeed_importer_fnc__edit_account'
	);
	add_submenu_page( null,
					 'Import from account',
					 'Import from account',
					 'edit_posts',
					 'accounts-import-aio-importer',
					 'aioifeed_importer_fnc__import_from_account'
	);
}



/**
 * Show the administration page with the list of the accounts created by the user.
 *
 * @return void
 */
function aioifeed_importer_fnc__accounts() {
	include('accounts.php');
}



/**
 * Show the administration page where the user can edit the account details.
 *
 * @return void
 */
function aioifeed_importer_fnc__edit_account() {
	include('accounts-edit.php');
}



/**
 * Show the page with the list of the post to import (and imported).
 *
 * @return void
 */
function aioifeed_importer_fnc__import_from_account() {
	include('accounts-import.php');
}



/**
 * Return the list of the user accounts.
 *
 * @return array
 */
function aioifeed_get_socials() {
	global $wpdb;

	$query = "
		SELECT *
		FROM $wpdb->options
		WHERE option_name LIKE 'aioi_importer_account_%'
	";

	$res = $wpdb->get_results($query);

	$accounts = array();

	foreach($res as $r) {
		$key = str_replace('aioi_importer_account_', '', $r->option_name);
		$accounts[$key] = unserialize($r->option_value);
		$accounts[$key]['option_name'] = $r->option_name;
	}

	ksort($accounts);

	return $accounts;
}


/**
 * Handy function for validating and sanitizong a values from HTTP requests.
 *
 * @param string $elem
 * @param string $type
 * @return string
 */
function aioifeed_sanitize($elem, $type=null)
{
	// absolute integer
	if ($type == 'absint') return absint($elem);

	// array of string
	else if ($type == 'array_of_str') {
		$sanitized = array();

		if (is_array($elem)) {
			foreach ($elem as $key => $val) {
				$sanitized[ absint($key) ] = sanitize_text_field( trim($val) );
			}
		}

		return $sanitized;
	}

	// if the type is not specified it returns the element sanitized
	return sanitize_text_field( trim($elem) );
}



/**
 * Echo the HTML of a dismissible box with the given message inside as parameter.
 *
 * @param string $message
 * @param string $type
 * @param boolean $echo
 * @param boolean $details
 * @return string
 */
function aioifeed_echo_message($message, $type='info', $echo=true, $details=false) {
	if ($type == 'success') $class = 'notice-success';
	else if ($type == 'error') $class = 'notice-error';
	else if ($type == 'warning') $class = 'notice-warning';
	else $class = 'notice-info';

	$html = '
		<div id="message" class="notice '.$class.' is-dismissible">
			<p>
				'.$message;

				if (!empty($details)) {
					$html .= '
						<br />
						<a href="#" id="aioi_details_toggle">Details</a>
						<div style="display:none;" id="aioi_deitals_content">'.$details.'</div>
						<script>
							jQuery("#aioi_details_toggle").click(function() {
								jQuery("#aioi_deitals_content").slideToggle();
							});

						</script>
					';
				}

				$html .= '
			</p>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
	';

	if (!$echo) return $html;
	else {
		echo $html;
	}
}



/**
 * Retrieve a post from the social identifier.
 * The social idenfier is a code made of [social_network_type]_[social_news_id]
 * for instance it looks like facebook_613287632186931 where that big number is
 * the ID assigned by facebook to the news.
 *
 * @param string $feed_id
 * @param string $account
 * @param string $aioi_post_type
 * @return array|object|null
 */
function aioifeed_get_post_from_identifier($feed_id, $account, $aioi_post_type='post') {
	$identifier = $account['aioi_social_type'] .'_'. $feed_id;

	global $wpdb;

	$query = "
		SELECT *
		FROM $wpdb->postmeta
		WHERE meta_key LIKE '_aioi_post_identifier'
		  AND meta_value LIKE '$identifier'
	";

	return $wpdb->get_results($query);
}



/**
 * Get the form structure: this is a form builder. You can add a new form for
 * future implementations (like linkedin) simply setting a new entry in the array.
 * The form builder will make for you the HTML form.
 *
 * @param string $platform
 * @return array
 */
function aioifeed_get_social_structure($platform=null) {
	$structure = array();

	$structure['facebook'] = aioifeed_get_facebook_field_settings();

	$structure['instagram'] = aioifeed_get_instagram_field_setting();

	$structure['twitter'] = aioifeed_get_twitter_field_setting();

	// $structure['linkedin']['fields'] = array(
	// 	'access_token' => array('label' => 'Access Token', 'name' => 'access_token', 'type' => 'text')
	// );

	if (!empty($platform)) {
		if (isset($structure[$platform])) return $structure[$platform];
		return null;
	}

	return $structure;
}



/**
 * Check the required fields are completed.
 *
 * @param array $post
 * @return string|boolean
 */
function aioi_check_required_fields_on_post($post)
{
	$social_type = sanitize_text_field($post['social_type']);
	$structure = aioifeed_get_social_structure($social_type);

	if (empty($post['aioi_name'])) {
		return 'Please fill up all the required fields marked with a red star.';
	}
	else if ($structure and isset($structure['fields'])) {
		foreach($structure['fields'] as $field_struct) {
			$post_field_name = 'aioi_field_' . $field_struct['name'];
			if ($field_struct['required'] and empty($post[$post_field_name])) {
				return 'Please fill up all the required fields marked with a red star.';
			}
		}
	}
	// the user tried to insert a social network not in the list
	else return 'Social network not valid!';

	return false;
}



/**
 * Get the twitter structure for the form builder.
 *
 * @return array
 */
function aioifeed_get_twitter_field_setting()
{
	$settings['fields'] = array(
		'key' => array(
			'label' => 'Key',
			'name' => 'key',
			'type' => 'text',
			'class' => 'large-input',
			'required' => true,
			'hint' => false,
		),
		'secret' => array(
			'label' => 'Secret',
			'name' => 'secret',
			'type' => 'text',
			'class' => 'large-input',
			'required' => true,
			'hint' => false,
		),
		'token' => array(
			'label' => 'Token',
			'name' => 'token',
			'type' => 'text',
			'class' => 'large-input',
			'required' => false,
			'hint' => false,
		),
		'token_secret' => array(
			'label' => 'Token Secret',
			'name' => 'token_secret',
			'type' => 'text',
			'class' => 'large-input',
			'required' => false,
			'hint' => false,
		),
		'screen_name' => array(
			'label' => 'Twitter user (screen name)',
			'name' => 'screen_name',
			'type' => 'text',
			'class' => '',
			'required' => false,
			'hint' => false,
		)
	);

	return $settings;
}



/**
 * Get the Instagram structure for the form builder.
 *
 * @return array
 */
function aioifeed_get_instagram_field_setting()
{
	$settings['notes'] = '<b>NOTES:</b> in your Instagram APP setting make sure that:<br />
										&bull; The option <i>Implicit Authentication</i> is checked<br />
										&bull; The redirect url is correct';
	$settings['fields'] = array(
		 'client_id' => array(
				'label' => 'Client ID',
				'name' => 'client_id',
				'type' => 'text',
				'class' => 'large-input',
				'required' => true,
				'hint' => false,
		),
		'client_secret' => array(
			'label' => 'Client Secret',
			'name' => 'client_secret',
			'type' => 'text',
			'class' => 'large-input',
			'required' => true,
			'hint' => false,
		),
		'token' => array(
			'label' => 'Access Token',
			'name' => 'token',
			'type' => 'text',
			'class' => 'large-input',
			'required' => false,
			'hint' => false,
		),
		'page_id' => array(
			'label' => 'Page ID',
			'name' => 'page_id',
			'type' => 'text',
			'class' => '',
			'required' => false,
			'hint' => false,
		),
	);

	return $settings;
}



/**
 * Get the Facebook structure for the form builder.
 *
 * @return array
 */
function aioifeed_get_facebook_field_settings()
{
	$settings['fields'] = array(
		 'key' => array(
			 'label' => 'App ID',
			 'name' => 'key',
			 'type' => 'text',
			 'class' => 'large-input',
			 'hint' => 'here hint field text that will appear beneath the feald',
			 'required' => true,
			 'hint' => false,
		),
		'secret' => array(
			'label' => 'App Secret',
			'name' => 'secret',
			'type' => 'text',
			'class' => 'large-input',
			'required' => true,
			'hint' => false,
		),
		'token' => array(
			'label' => 'Token',
			'name' => 'token',
			'type' => 'text',
			'class' => 'large-input',
			'required' => false,
			'hint' => false,
		),
		'page_id' => array(
			'label' => 'Page ID',
			'name' => 'page_id',
			'type' => 'text',
			'class' => '',
			'required' => true,
			'hint' => false,
		)
	);

	return $settings;
}



/**
 * Get the form builder notes for a given social platform.
 *
 * @return string
 * @return array|null
 */
function aioifeed_get_social_notes($platform) {
	$structure = aioifeed_get_social_structure($platform);

	if (!empty($structure['notes'])) return $structure['notes'];

	return null;
}



/**
 * Get the form builder fields details for a given social platform.
 *
 * @return string $platform
 * @return array|null
 */
function aioifeed_get_fields_structure($platform) {
	$structure = aioifeed_get_social_structure($platform);
	if (isset($structure['fields'])) return $structure['fields'];
	return null;
}



/**
 * Return the HTML for the selects Post Status and Post comments.
 *
 * @return string $name
 * @return string $type_select
 * @return string $class
 * @return string $default
 * @return string|null
 */
function aioifeed_get_select($name, $type_select, $class='', $default=null) {
	if ($type_select == 'post_status') $values = array('draft', 'publish');
	else if ($type_select == 'post_comments') $values = array('closed', 'open');
	else return null;

	$html = '<select name="'.$name.'" id="'.$name.'" class="'.$class.'">';
	foreach ($values as $value) {
		if ($default and ($default == $value)) $selected = ' selected="selected" ';
		else $selected = '';

		$html .= '<option value="'.$value.'" '.$selected.'>'.ucwords($value).'</option>';
	}
	$html .= '</select>';

	return $html;
}



/**
 * Return the javascript script for the redirect.
 *
 * @return string $link
 * @return numeric $seconds
 * @return boolean $echo
 * @return string|null
 */
function aioifeed_echo_redirect_script($link, $seconds=5, $echo=true) {
	$html =  '
			<script>
				setTimeout(function () {
					window.location.href = "'.$link.'";
				}, '.($seconds*1000).');
			</script>
	';

	if ($echo) echo $html;

	return $html;
}



/**
 * Return the HTML code for the select with all the post types.
 *
 * @param string $name
 * @param string $class
 * @param string $default
 * @return string
 */
function aioifeed_get_select_post_types($name, $class='', $default='post') {
	$post_types = get_post_types();

	$html = '
		<select class="'.$class.'" name="'.$name.'" id="'.$name.'">';

			foreach($post_types as $type) {
				if ($default == $type) $selected = ' selected="selected" ';
				else $selected = '';

				$html .= '<option value="'.$type.'" '.$selected.'>'.$type.'</option>';
			}

			$html .= '
		</select>';

	return $html;
}



/**
 * Get the feeds for a given slug account.
 *
 * @param string $slug_accout
 * @param numeric $post_number
 * @return string
 */
function aioifeed_get_feeds($slug_accout, $post_number=10) {
	include_once(plugin_dir_path(__FILE__) . 'app/importer.php');
	$account = get_option($slug_accout);

	if (!empty($account)) {
		$api = new WpSocialImporter($account);
		$par['number_of_post'] = $post_number;

		$result = $api->getPosts($par);
		$feeds = isset($result['data']) ? $result['data'] : null;

		return $feeds;
	}

	return null;
}



/**
 * Get the latest feed for a given account slug
 *
 * @return array
 */
function aioifeed_get_last_feed($slug_account) {
	$feeds = aioifeed_get_feeds($slug_account, 1);
	if (isset($feeds[0])) return $feeds[0];
	return null;
}



/**
 * Create a unique generic account identifier.
 *
 * @return string
 */
function aioifeed_create_slug() {
	$slug = 'aioi_importer_account_'. rand(1,9999) .'_'. uniqid();
	return $slug;
}



/**
 * Get the image preview for a vimeo video url.
 *
 * @param string $video_url
 * @return string
 */
function aioifeed_get_vimeo_preview($video_url) {
	$image_url = null;

	$ex = explode('vimeo.com/', $video_url);
	if (isset($ex[1])) {
		$ex = explode('#', $ex[1]);
		$ex = explode('&', $ex[0]);
		$ex = explode('?', $ex[0]);

		$video_code = $ex[0];

		$url = "http://vimeo.com/api/v2/video/$video_code.json";

		$resp = @file_get_contents($url);
		$resp = @json_decode($resp, true);

		if (isset($resp[0])) $resp = $resp[0];

		if (isset($resp['thumbnail_large'])) $image_url = $resp['thumbnail_large'];
		else if (isset($resp['thumbnail_medium'])) $image_url = $resp['thumbnail_medium'];
		else if (isset($resp['thumbnail_small'])) $image_url = $resp['thumbnail_small'];
	}

	return $image_url;
}



/**
 * Get the image preview for a youtube video url.
 *
 * @param string $video_url
 * @return string
 */
function aioifeed_get_youtube_thumbnail($video_url) {
	$image_url = null;

	$ex = explode('v=', $video_url);
	if (isset($ex[1])) {
		$ex = explode('#', $ex[1]);
		$ex = explode('&', $ex[0]);
		$ex = explode('?', $ex[0]);

		$ex_code = explode('&', $ex[0]);
		$image_url = 'https://i.ytimg.com/vi/'.$ex_code[0].'/maxresdefault.jpg';
	}

	return $image_url;
}



/**
 * Get from the given full url the external url parameter. For instance:
 * http://l.facebook.com/?url=www.ext-url.com&otherpar=1
 * it return www.ext-url.com
 *
 * @param string $url
 * @return string|null
 */
function aioifeed_get_facebook_external_url_param($url) {
	$parts = parse_url($url);

	parse_str($parts['query'], $parsed);
	if (!empty($parsed['url'])) return $parsed['url'];

	return null;
}



/**
 * Nonce securety check: break the application flow if the nonce is not right.
 *
 * @param string $action
 * @return void
 */
function aioi_nonce_check($action)
{
	$nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : null;
	if (!$nonce or !wp_verify_nonce($nonce, $action) ) die( 'Failed security check' );
}



/**
 * Return the current full url. If the NONCE action is specified it will return
 * the current url with the nonce parameter attached.
 *
 * @param string $nonce_action
 * @return string
 */
function aioi_current_url($nonce_action=null)
{
	$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	if ($nonce_action) return wp_nonce_url($url, $nonce_action);

	return $url;
}



/**
 * Helper function: store the access token after the user gave the authorization
 * to the Instagram app. Instagram give the access token through a hash in the url
 * eg: http://www..yoursite.com/ ... /wp-admin.php?par=1#access_token=89ewq37987d9821ewq893219
 * In this case this function receives the string #access_token=89ewq37987d9821ewq893219
 * as $hash; then it takes that code and stores in the given option.
 *
 * @param string $option_name
 * @param array $account
 * @param string $hash
 * @return void
 */
function aioi_store_access_token($option_name, $account, $hash)
{
	$hash = aioifeed_sanitize($hash);

	if (strpos($hash, 'access_token=') !== false) {
		$ex = explode('access_token=', $hash);
		if (count($ex) == 2) {
			$account['token'] = $ex[1];

			// checking if there are others hashes
			if (strpos($account['token'], '#') !== false) {
				$ex = explode('#', $account['token']);
				$account['token'] = $ex[0];
			}

			update_option($option_name, $account);
		}
	}
}



/**
 * Save the social network details coming from the create account form.
 *
 * @param string $slug
 * @param array $post
 * @return void
 */
function aioi_save_social_details_from_post($slug, $post)
{
	// taking all the account details from $post
	$social_details = array();
	$social_details['aioi_slug'] = $slug;
	$social_details['aioi_name'] = aioifeed_sanitize($post['aioi_name']);
	$social_details['aioi_post_type'] = aioifeed_sanitize($post['aioi_post_type']);
	$social_details['aioi_social_type'] = aioifeed_sanitize($post['social_type']);
	$social_details['aioi_post_comments'] = aioifeed_sanitize($post['aioi_post_comments']);
	$social_details['aioi_post_status'] = aioifeed_sanitize($post['aioi_post_status']);
	$social_details['aioi_created'] = date('Y-m-d H:i:s');

	foreach ($post as $key => $value) {
		// getting the field which field name starts with aioi_field_
		if (strpos($key, 'aioi_field_') !== false) {
			$key = str_replace('aioi_field_', '', aioifeed_sanitize($key));
			$social_details[$key] = aioifeed_sanitize($value);
		}
	}

	update_option($slug, $social_details);
}



/**
 * Update the social network details coming from the edit page.
 *
 * @param array $account
 * @param array $post
 * @return void
 */
function aioi_update_social_details_from_post($account, $post)
{
	$account['aioi_name'] = aioifeed_sanitize($post['aioi_name']);
	$account['aioi_post_type'] = aioifeed_sanitize($post['aioi_post_type']);
	$account['aioi_post_status'] = aioifeed_sanitize($post['aioi_post_status']);
	$account['aioi_post_comments'] = aioifeed_sanitize($post['aioi_post_comments']);
	$account['aioi_categories'] = aioifeed_sanitize($post['aioi_categories']);

	foreach ($post as $key => $value) {
		if (strpos($key, 'aioi_field_') !== false) {
			$key = str_replace('aioi_field_', '', aioifeed_sanitize($key));
			$account[$key] = aioifeed_sanitize($value);
		}
	}
	update_option( aioifeed_sanitize($post['option_name_save']), $account);

	$message['result'] = 'success';
	$message['message'] = 'Account saved successfully';
	$message['details'] = null;

	return $message;
}




// Handy function for the development
// function dd($content=null, $stop=true) {
// 	echo '<pre>';
// 	print_r($content);
// 	echo '</pre>';
//
// 	if ($stop) die();
// }
// function cc($content=null) {
// 	echo ' <!-- TEST TEST TEST ';
// 	print_r($content);
// 	echo ' --> ';
// }
