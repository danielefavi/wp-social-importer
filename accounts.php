<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

wp_enqueue_style('aioi-style', plugins_url('css/aioi_style.css',__FILE__));

$success = $error = null;

$accounts = aioifeed_get_socials();


if (isset($_POST['social_type'])) {
	aioi_nonce_check('aioi_create_account');

	$error = aioi_check_required_fields_on_post($_POST);

	if (!$error) {
		// genereting the slug
		$slug = aioifeed_create_slug();

		if (isset($accounts[$slug])) $error = 'The slug <b>'.$slug.'</b> already exists.';
		else {
			aioi_save_social_details_from_post($slug, $_POST);

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
														<td><?php echo esc_html($account['aioi_name']); ?></td>
														<!-- td><?php echo esc_html($account['aioi_slug']); ?></td -->
														<td><?php echo esc_html($account['aioi_social_type']); ?></td>
														<td class="hide-mobile"><?php echo esc_html($account['aioi_post_type']); ?></td>
														<!-- td><?php echo esc_html($account['aioi_created']); ?></td -->
														<td>
															<div class="action-links">
																<a href="<?php menu_page_url('accounts-import-aio-importer'); ?>&option_name=<?php echo esc_html($account['option_name']); ?>">IMPORT</a>
																&bull;
																<a href="<?php menu_page_url('accounts-edit-aio-importer'); ?>&option_name=<?php echo esc_html($account['option_name']); ?>">EDIT</a>
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
													<?php wp_nonce_field('aioi_create_account'); ?>
													<p>
														<strong><span class="required-star"> * </span>Name</strong>
														<br />
														<input type="text" id="name" name="aioi_name" />
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
