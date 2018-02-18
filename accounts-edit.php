<?php
	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


	wp_enqueue_style('aioi-style', plugins_url('css/aioi_style.css',__FILE__));

	$account = $message = null;
	$hide = false;

	// getting the account name from the URL parameter
	if ( isset($_GET['option_name']) and (strpos($_GET['option_name'], 'aioi_importer_account_') !== false) ) {
		$option_name = aioifeed_sanitize($_GET['option_name']);

		$account = get_option($option_name);
	}
	else {
		// in case of not valid account name it redirects to the main account list
		aioifeed_echo_redirect_script(menu_page_url('accounts-aio-importer', false), 3);

		$message['result'] = 'error';
		$message['message'] = 'Account does not exist';
		$message['details'] = null;

		$hide = true;
	}

	if (empty($account)) {
		// in case of empty account it redirects to the main account list
		aioifeed_echo_redirect_script(menu_page_url('accounts-aio-importer', false), 3);

		$message['result'] = 'error';
		$message['message'] = 'Account does not exist';
		$message['details'] = null;

		$hide = true;
	}
	else {
		// this is executed when the user press the SAVE ACCOUNT button
		if (isset($_POST['option_name_save'])) {
			aioi_nonce_check('aioi_edit_action');

			$error = false;

			// checking if the required fields are completed
			if ($error_message = aioi_check_required_fields_on_post($_POST)) {
				$message['result'] = 'error';
				$message['message'] = $error_message;
				$message['details'] = null;
				$error = true;
			}

			// if no errors occurred let's save
			if (!$error) {
				$message = aioi_update_social_details_from_post($account, $_POST);

				$account = get_option( $option_name );
			}
		}

		// in case the user pressed the DELETE ACCOUNT button
		else if (isset($_POST['delete_account'])) {
			// nonce security check
			aioi_nonce_check('aioi_delete_action');

			delete_option($option_name);

			// redirecting to the account list after 5 seconds
			aioifeed_echo_redirect_script(menu_page_url('accounts-aio-importer', false), 5);

			$message['result'] = 'success';
			$message['message'] = 'Account deleted successfully';
			$message['details'] = null;

			$hide = true;
		}
	}

	// getting the fields list needed to the form builder in order
	// to build dinamically the accounts input forms
	$fields = aioifeed_get_fields_structure($account['aioi_social_type']);
	$notes = aioifeed_get_social_notes($account['aioi_social_type']);
?>

<div class="wrap">

	<div class="aioi-header">
		<h1>Edit <?php echo $account['aioi_name']; ?></h1>

		<?php
			if ($message) {
				aioifeed_echo_message($message['message'], $message['result'], true, $message['details']);
			}

			if (!$hide) {
		?>

		<p class="go-back-link">
			<a href="<?php menu_page_url('accounts-aio-importer'); ?>" class="button">Back to Account List</a>
			<?php
				if (isset($_GET['option_name'])) {
					?>
						<a href="<?php menu_page_url('accounts-import-aio-importer'); ?>&option_name=<?php echo esc_html($option_name); ?>" class="button button-primary button-large">Import from this account</a>
					<?php
				}
			?>
		</p>

		<div class="clear"></div>
	</div>
	<hr />

	<div class="fieldset-account">
		<?php
			if (!empty($notes)) {
				echo '<p class="notes">'.$notes.'</p><hr />';
			}
		?>

		<form method="post">
			<?php wp_nonce_field('aioi_edit_action'); ?>
			<input type="hidden" name="social_type" value="<?php echo esc_html($account['aioi_social_type']) ?>">

			<p><strong>Slug:</strong> <?php echo esc_html($account['aioi_slug']); ?></p>
			<p><strong>Social:</strong> <?php echo ucwords( esc_html($account['aioi_social_type']) ); ?></p>
			<p><strong>Created:</strong> <?php echo esc_html($account['aioi_created']); ?></p>

			<p>
				<strong><span class="required-star"> * </span>Name</strong>
				<br />
				<input type="text" id="slug" name="aioi_name" value="<?php echo esc_html($account['aioi_name']); ?>" />
			</p>
			<p>
				<strong>Import with post type</strong>
				<br />
				<?php echo aioifeed_get_select_post_types('aioi_post_type', '', $account['aioi_post_type']); ?>
			</p>
			<p>
				<strong>Import with post status</strong>
				<br />
				<?php echo aioifeed_get_select('aioi_post_status', 'post_status', '', $account['aioi_post_status']); ?>
			</p>
			<p>
				<strong>Import with comments</strong>
				<br />
				<?php echo aioifeed_get_select('aioi_post_comments', 'post_comments', '', $account['aioi_post_comments']); ?>
			</p>
			<p>
				<strong>Categories</strong>
				<br />
				<input type="text" name="aioi_categories" value="<?php echo (isset($account['aioi_categories']) ? esc_html($account['aioi_categories']) : null); ?>" />
				<br />
				<small>Category list comma separated</small>
			</p>

			<?php
				foreach($fields as $field) {
					$required_html = $hint_html = '';

					$field_val = isset($account[$field['name']]) ? $account[$field['name']] : null;
					if ($field['required']) {
						$required_html = '<span class="required-star"> * </span>';
					}
					if (!empty($field['hint'])) $hint_html = '<small><br />'.$field['hint'].'</small>';

					?>
						<p>
							<strong><?php echo $required_html . $field['label']; ?></strong>
							<br />
							<input type="<?php echo $field['type']; ?>" id="<?php echo $field['name']; ?>" name="aioi_field_<?php echo esc_html($field['name']); ?>" value="<?php echo esc_html($field_val); ?>" class="<?php echo $field['class']; ?>" />
							<?php echo $hint_html; ?>
						</p>
					<?php
				}
			?>
			<input type="hidden" name="option_name_save" value="<?php echo $option_name; ?>" />

			<br />
			<div class="float-left">
				<input name="save" class="butt<n button-primary button-large" value="Save <?php echo ucwords($account['aioi_social_type']); ?> account" type="submit" />
			</div>
		</form>

		<div class="delete-button delete-button-account">
			<form method="post">
				<?php wp_nonce_field('aioi_delete_action'); ?>
				<input name="delete" class="delete-button" value="Delete account" type="submit" onclick="return confirm('Are you sure?')" />
				<input type="hidden" name="delete_account" value="<?php echo $option_name; ?>" />
			</form>
		</div>

		<div class="clear"></div>

	</div>


	<?php } ?>
</div>
