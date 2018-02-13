<?php
wp_enqueue_style('aioi-style', plugins_url('css/aioi_style.css',__FILE__));

$success = $error = null;

$accounts = aioifeed_get_socials();


if (isset($_POST['social_type'])) {
	$error = false;

	if (!empty($_POST['required_fields'])) {
		foreach($_POST['required_fields'] as $req_field) {
			if (empty($_POST[$req_field])) {
				$error = 'Please fill up all the required fields marked with a red star.';
			}
		}
	}

	if (!$error) {

		// genereting the slug
		$slug = aioifeed_create_slug();
		if (isset($accounts[$slug])) {
			for($i=0; $i<20; $i++) {
				$slug = aioifeed_create_slug();
				break;
			}
		}

		if (isset($accounts[$slug])) $error = 'The slug <b>'.$slug.'</b> already exists.';
		else {
			$social_details = array();
			$social_details['aioi_slug'] = $slug;
			$social_details['aioi_name'] = $_POST['aioi_name'];
			$social_details['aioi_post_type'] = $_POST['aioi_post_type'];
			$social_details['aioi_social_type'] = $_POST['social_type'];
			$social_details['aioi_post_comments'] = $_POST['aioi_post_comments'];
			$social_details['aioi_post_status'] = $_POST['aioi_post_status'];
			$social_details['aioi_created'] = date('Y-m-d H:i:s');

			foreach ($_POST as $key => $value) {
				if (strpos($key, 'aioi_field_') !== false) {
					$key = str_replace('aioi_field_', '', $key);
					$social_details[$key] = $value;
				}
			}

			update_option($slug, $social_details);

			$accounts = aioifeed_get_socials();
		}
	} // end if (!$error)
}



$sn_fields = aioifeed_get_social_structure();
?>

<div class="wrap">
	<h1>Accounts</h1>

	<?php
		if ($error) aioifeed_echo_message($error, 'error');
	?>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">

			<div id="post-body" class="metabox-holder columns-2" style="width: 100%;">
				<div style="position: relative;" id="post-body-content">
					<?php
						if (empty($accounts) or (count($accounts) == 0)) {
							echo '<h2>No accouns setted yet</h2>';
						}
						else {
							?>
								<table id="accounts-table" class="wp-list-table widefat fixed striped pages">
									<thead>
										<tr>
											<th scope="col" class="manage-column column-author">Name</th>
											<!-- th scope="col" class="manage-column column-author">Slug</th -->
											<th scope="col" class="manage-column column-author">Social</th>
											<th scope="col" class="manage-column column-author hide-mobile">Post Type</th>
											<!-- th scope="col" class="manage-column column-author">Created</th -->
											<th scope="col" class="manage-column column-author">Actions</th>
										</tr>
									</thead>

									<tbody id="the-list">
										<?php
											foreach($accounts as $account) {
												?>
													<tr>
														<td><?php echo $account['aioi_name']; ?></td>
														<!-- td><?php echo $account['aioi_slug']; ?></td -->
														<td><?php echo $account['aioi_social_type']; ?></td>
														<td class="hide-mobile"><?php echo $account['aioi_post_type']; ?></td>
														<!-- td><?php echo $account['aioi_created']; ?></td -->
														<td>
															<div class="action-links">
																<a href="<?php menu_page_url('accounts-import-aio-importer'); ?>&option_name=<?php echo $account['option_name']; ?>">IMPORT</a>
																&bull;
																<a href="<?php menu_page_url('accounts-edit-aio-importer'); ?>&option_name=<?php echo $account['option_name']; ?>">EDIT</a>
															</div>
														</td>
													</tr>
												<?php
											}
										?>
									</tbody>
								</table>
							<?php
						}
					?>
				</div><!-- end of #post-body-content -->
			</div><!-- end of #post-body -->


			<div id="postbox-container-1" class="postbox-container">

				<div id="submitdiv" class="postbox ">
					<h2 class="hndle ui-sortable-handle"><span>Add new account</span></h2>
					<div class="inside">
						<div class="submitbox" id="submitpost">
							<div id="minor-publishing">
								<p>
									<strong>Select a social network type &nbsp;</strong>
								<p>
								</p>
									<select name="social_network" id="social_network">
										<option value=""></option>
										<option value="facebook">Facebook</option>
										<option value="instagram">Instagram</option>
										<!-- option value="linkedin">LinkedIn</option -->
										<option value="twitter">Twitter</option>
									</select>
								</p>


								<?php
									$html_select_post_types = aioifeed_get_select_post_types('aioi_post_type');

									foreach($sn_fields as $social_name => $sn_data) {
										$fieldset = $sn_data['fields'];
										?>
											<div id="<?php echo $social_name; ?>-fieldset" class="social-fieldset">
												<?php
													if (!empty($sn_data['notes'])) {
														echo '<hr /><p class="notes-small">'.$sn_data['notes'].'</p><hr />';
													}
												?>
												<form method="post" name="<?php echo $social_name; ?>">
														<p>
															<strong><span class="required-star"> * </span>Name</strong>
															<br />
															<input type="text" id="name" name="aioi_name" />
															<input type="hidden" name="required_fields[]" value="aioi_name" />
														</p>
														<p>
															<strong>Assign post type</strong>
															<br />
															<?php echo $html_select_post_types; ?>
														</p>
														<p>
															<strong>Import with post status</strong>
															<br />
															<?php echo aioifeed_get_select('aioi_post_status', 'post_status'); ?>
														</p>
														<p>
															<strong>Import with comments</strong>
															<br />
															<?php echo aioifeed_get_select('aioi_post_comments', 'post_comments'); ?>
														</p>

														<?php
															foreach($fieldset as $field) {
																$required_html = $hint_html = '';
																if ($field['required']) {
																	$required_html = '<span class="required-star"> * </span>';
																	?>
																		<input type="hidden" name="required_fields[]" value="aioi_field_<?php echo $field['name']; ?>" />
																	<?php
																}
																if (!empty($field['hint'])) $hint_html = '<small><br />'.$field['hint'].'</small>';
																?>
																	<p>
																		<strong><?php echo $required_html . $field['label']; ?></strong>
																		<br />
																		<input type="<?php echo $field['type']; ?>" id="<?php echo $field['name']; ?>" name="aioi_field_<?php echo $field['name']; ?>" class="<?php echo $field['class']; ?>" />
																		<?php echo $hint_html; ?>
																	</p>
																<?php
															}
														?>
														<input type="hidden" name="social_type" value="<?php echo $social_name; ?>" />

														<div class="social-fieldset-submit">
															<input name="save" class="button button-primary button-large" value="Save <?php echo ucwords($social_name); ?> account" type="submit">
														</div>
												</form>
											</div>
										<?php
									} // end foreach $sn_fields
								?>
							</div><!-- end of #minor-publishing -->
						</div><!-- end of #submitpost-->
					</div><!-- end of .inside -->
				</div><!-- end of #submitdiv -->

			</div>
		</div>

		<br class="clear">
	</div><!-- /poststuff -->

</div><!-- end .wrap -->



<script>

	jQuery('#social_network').change(function() {
		jQuery('.social-fieldset').hide();
		var fieldid = '#' + jQuery(this).val() + '-fieldset';
		jQuery(fieldid).fadeIn();
	});


</script>
