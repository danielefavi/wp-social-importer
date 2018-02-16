<?php
	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


	wp_enqueue_style('aioi-style', plugins_url('css/aioi_style.css',__FILE__));
	$account = null;
	$hide = false;


	if ( isset($_GET['option_name']) and (strpos($_GET['option_name'], 'aioi_importer_account_') !== false) ) {
		$option_name = aioifeed_sanitize($_GET['option_name']);

		$account = get_option($option_name);

		/*
		 * Saving Instagram access_token in callback
		 * Intagram gives the access token via URL in callback. The problem is that Instagram puts
		 * the access_token after an hash so the server cannot read the url fragment after #
		 * The solution is make the client read the fragmented url
		 */
		if (isset($_GET['aioi_inst_at']) and !empty($account) and (!isset($_GET['token_redirected']))) {

			if (isset($_POST['hash'])) {
				$hash = aioifeed_sanitize($_POST['hash']);

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
			} // end if (isset($_POST['hash']))

			else if (isset($_GET['code']) and ($account['token'] != $_GET['code'])) {
				$account['token'] = aioifeed_sanitize($_GET['code']);
				update_option($option_name, $account);
			}
			else if (isset($_GET['access_token']) and ($account['token'] != $_GET['access_token'])) {
				$account['token'] = aioifeed_sanitize($_GET['access_token']);
				update_option($option_name, $account);
			}
			else {
				$hide = true;
				$current_url =  aioi_current_url();

				?>
					<script>
						jQuery(document).ready(function() {

							jQuery.ajax({
								url: '<?php echo $current_url; ?>',
								type: "POST",
								data : { hash : window.location.hash },
								success: function () {
									window.location.href = '<?php echo $current_url .= '&token_redirected=success'; ?>';
								},
								error: function () {
									window.location.href = '<?php echo $current_url .= '&token_redirected=error'; ?>';
								},
								async: false
							});

						});
					</script>
					Wait a moment, please... Saving the access token.<br />
					If you will not be redirected <a href="<?php echo $redirect_url; ?>">click here</a>
				<?php
			}

		}
	}
	else {
		wp_redirect(menu_page_url('accounts-aio-importer', false));
		echo '<script> window.location.href = "'.menu_page_url('accounts-aio-importer').'"; </script>';
		$hide = true;
	}

	if (empty($account)) {
		wp_redirect(menu_page_url('accounts-aio-importer', false));
		echo '<script> window.location.href = "'.menu_page_url('accounts-aio-importer').'"; </script>';
		$hide = true;
	}



if (!$hide) {

	include_once(plugin_dir_path(__FILE__) . 'app/importer.php');

	$api = new Importer($account);

	// default parameters
	//$parameters['to_date'] = !empty($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
	//$parameters['from_date'] = !empty($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-d', strtotime(date('Y-m-d') . ' -30 days'));
	if (isset($_POST['number_of_post'])) {
		$nop = aioifeed_sanitize($_POST['number_of_post'], 'absint');
		$parameters['number_of_post'] = (($nop > 1) and ($nop < 200)) ? $nop : 50;
	}
	else $parameters['number_of_post'] = 50;


	if (isset($_POST['perform_import']) or isset($_POST['perform_import_head'])) {
		if (!empty($_POST['aioi_import_chk'])) {
			$social_post_ids = aioifeed_sanitize($_POST['aioi_import_chk'], 'array_of_str');
			$response_import = $api->importPosts($social_post_ids, $parameters);
		}
	}

	$posts = array();
	$result = $api->getPosts($parameters);

	$posts = isset($result['data']) ? $result['data'] : null;
?>

<div class="wrap">

	<div class="aioi-header">
		<h1><?php echo $account['aioi_name']; ?></h1>

		<p class="go-back-link">
			<a href="<?php menu_page_url('accounts-aio-importer'); ?>" class="button">Back to Account List</a>
			<a href="<?php menu_page_url('accounts-edit-aio-importer'); ?>&option_name=<?php echo $option_name; ?>" class="button button-primary button-large">Edit this account</a>
		</p>

		<div class="clear"></div>
	</div>

	<hr />

	<?php
		if (isset($response_import)) {

			if (is_array($response_import)) {
				if (count($response_import) > 0) {
					$message = 'Posts imported successfully: <ul>';
					foreach($response_import as $wp_id) {
						$link = get_edit_post_link($wp_id);
						$message .= '<li><a href="'.$link.'" target="_blank">Post ID '.$wp_id.'</a> </li>';
					}
					$message .= '</ul>';
					$message = substr($message, 0, -2);
					aioifeed_echo_message($message, 'success');
				}
				else aioifeed_echo_message('Nothing imported.');
			}
			else if (is_string($response_import)) {
				aioifeed_echo_message($response_import);
			}

		}

		if (!$result['success']) {
			$details = null;

			if (isset($result['error'])) {
				$details .= '<pre>'.print_r($result['error'], true).'</pre>';
			}

			if (isset($result['html_error'])) $err_message = $result['html_error'];
			else {
				$err_message = 'An error occurred!';
			}

			aioifeed_echo_message($err_message, 'error', true, $details);
		}
	?>


	<div class="search-bar">
		<form method="post" name="search_form">
			<div class="search-field">
				<small>N. of posts:</small><br />
				<input type="number" name="number_of_post" id="number_of_post" value="<?php echo $parameters['number_of_post']; ?>" />
			</div>
			<?php /*
			<div class="search-field">
				<small>From:</small><br />
				<input type="text" name="from_date" id="from_date" value="<?php echo $parameters['from_date']; ?>" />
			</div>
			<div class="search-field">
				<small>To:</small><br />
				<input type="text" name="to_date" id="to_date" value="<?php echo $parameters['to_date']; ?>" />
			</div>
			*/ ?>

			<div class="search-field">
				<small>&nbsp;</small><br />
				<input type="submit" class="button" value="Search" name="search_button" />
			</div>
		</form>

	<?php
		if (count($posts) <= 0) {
			?>
				<div style="clear:both;"></div>
			</div>
			<?php
		}

		if (count($posts)) {
			?>

				<form method="post" name="import_posts" id="import_posts">

					<div class="search-field-right">
						<small>&nbsp;</small><br />
						<input name="perform_import_head" id="perform_import_head" class="button button-primary button-large" value="Import selected" type="submit" />
					</div>

				<div style="clear:both;"></div>
			</div>


				<br />

					<table id="accounts-table" class="wp-list-table widefat fixed striped pages">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-author hide-mobile hide-tablet">ID</th>
								<th scope="col" class="manage-column column-author hide-mobile hide-tablet">Created</th>
								<th scope="col" class="manage-column column-author">Title</th>
								<th scope="col" class="manage-column column-author hide-mobile">Post</th>
								<th scope="col" class="manage-column column-author hide-mobile">Featured Image</th>
								<th scope="col" class="manage-column column-author">Links</th>
								<th scope="col" class="manage-column column-author hide-mobile">Imported</th>
								<th scope="col" class="manage-column column-author column-checkbox">
									<input type="checkbox" id="check_all_chk" />
								</th>
							</tr>
						</thead>

						<tbody id="the-list">
							<?php
								foreach($posts as $res) {
									$prev_title = substr(strip_tags($res['title']), 0, 200);
									$prev_post = substr(strip_tags($res['post']), 0, 200);

									?>
										<tr>
											<td class="hide-mobile hide-tablet"><?php echo esc_html($res['id']); ?></td>
											<td class="hide-mobile hide-tablet"><?php echo esc_html($res['created_at']); ?></td>
											<td><?php echo esc_html($prev_title); ?></td>
											<td class="hide-mobile"><?php echo esc_html($prev_post); ?></td>
											<td class="hide-mobile">
												<?php
													if (!empty($res['featured_image'])) {
														?>
															<a href="<?php echo esc_html($res['featured_image']); ?>" target="_blank">
																<img src="<?php echo esc_html($res['featured_image']); ?>" />
															</a>
														<?php
													}
													else echo 'No image found';
												?>
											</td>
											<td>
												<?php
													$br = null;
													if (!empty($res['original_url'])) {
														$br = '<br /><br class="show-mobile" />';
														echo '<a href="'.esc_html($res['original_url']).'" target="_blank">Original Post</a>';
													}
													if (!empty($res['link_url']))
														echo $br . '<a href="'.esc_html($res['link_url']).'" target="_blank">Related Link</a>'
												?>
											</td>
											<td class="hide-mobile">
												<?php
													$post = aioifeed_get_post_from_identifier($res['id'], $account, $account['aioi_post_type']);

													if ($post) {
														$wp_post_id = $post[0]->post_id;
														$link = get_edit_post_link($wp_post_id);
														echo '<a href="'.$link.'" target="_blank" title="Click here to edit the post">IMPORTED</a>';
													}
												?>
											</td>
											<td>
												<?php if (!$post) { ?>
													<input type="checkbox" name="aioi_import_chk[]" class="aioi_import_chk_list" value="<?php echo $res['id']; ?>" />
												<?php } ?>
											</td>
										</tr>
									<?php
								}
							?>
						</tbody>

						<tfoot>
							<tr>
								<th scope="col" class="manage-column column-author hide-mobile hide-tablet">ID</th>
								<th scope="col" class="manage-column column-author hide-mobile hide-tablet">Created</th>
								<th scope="col" class="manage-column column-author">Title</th>
								<th scope="col" class="manage-column column-author hide-mobile">Post</th>
								<th scope="col" class="manage-column column-author hide-mobile">Featured Image</th>
								<th scope="col" class="manage-column column-author">Link</th>
								<th scope="col" class="manage-column column-author hide-mobile">WP ID</th>
								<th scope="col" class="manage-column column-author column-checkbox"></th>
							</tr>
						</tfoot>
					</table>

					<br /><hr />

					<p class="import-button">
						<input name="perform_import" id="perform_import" class="button button-primary button-large" value="Import selected" type="submit" />
					</p>

					<?php
						foreach($parameters as $key => $val) {
							echo '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
						}
					?>

				</form>

				<script>
					jQuery('#check_all_chk').click(function() {
						if (jQuery('#check_all_chk').is(':checked')) {
							jQuery('.aioi_import_chk_list').attr('checked','checked');
						}
						else {
							jQuery('.aioi_import_chk_list').attr('checked',false);
						}
					});
				</script>

			<?php
		}
		else {
			?>
				<h2>No posts in this account!</h2>
			<?php
		}
	?>

</div>

<?php
} // end hide page
?>
