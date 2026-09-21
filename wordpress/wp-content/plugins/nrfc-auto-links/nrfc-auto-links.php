<?php
/**
 * Plugin Name: NRFC Auto Links
 * Description: Automatically links specific text patterns to their respective URLs.
 * Version: 1.0.0
 * Author: NRFC Automation
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/class-nrfc-auto-links.php';

/**
 * Settings Page
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'NRFC Auto Links Settings',
		'NRFC Auto Links',
		'manage_options',
		'nrfc-auto-links',
		'nrfc_auto_links_render_settings_page'
	);
});

/**
 * Register Settings
 */
add_action( 'admin_init', function() {
	register_setting( 'nrfc_auto_links_group', 'nrfc_auto_links_settings' );
});

/**
 * Render Settings Page
 */
function nrfc_auto_links_render_settings_page() {
	$settings = get_option( 'nrfc_auto_links_settings', [] );
	$rules    = isset( $settings['rules'] ) ? $settings['rules'] : [];

	// Handle adding a new rule
	if ( isset( $_POST['nrfc_add_rule'] ) && check_admin_referer( 'nrfc_auto_links_action', 'nrfc_auto_links_nonce' ) ) {
		$rules[] = [
			'text'         => '',
			'link'         => '',
			'target_pages' => [],
		];
		$settings['rules'] = $rules;
		update_option( 'nrfc_auto_links_settings', $settings );
	}

	// Handle deleting a rule
	if ( isset( $_GET['delete_rule'] ) && check_admin_referer( 'nrfc_delete_rule_action' ) ) {
		$index = intval( $_GET['delete_rule'] );
		if ( isset( $rules[ $index ] ) ) {
			unset( $rules[ $index ] );
			$rules = array_values( $rules );
			$settings['rules'] = $rules;
			update_option( 'nrfc_auto_links_settings', $settings );
		}
	}

	$pages = get_pages();

	?>
	<div class="wrap">
		<h1>NRFC Auto Links Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'nrfc_auto_links_group' ); ?>
			<table class="widefat fixed" style="margin-bottom: 20px;">
				<thead>
					<tr>
						<th>Search Text</th>
						<th>Link Target Page</th>
						<th>Active Pages (Leave empty for all)</th>
						<th style="width: 100px;">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rules as $index => $rule ) : ?>
						<tr>
							<td>
								<input type="text" name="nrfc_auto_links_settings[rules][<?php echo $index; ?>][text]" value="<?php echo esc_attr( $rule['text'] ); ?>" class="regular-text" />
							</td>
							<td>
								<select name="nrfc_auto_links_settings[rules][<?php echo $index; ?>][link]">
									<option value=""><?php _e( '-- Select Page --' ); ?></option>
									<?php foreach ( $pages as $page ) : ?>
										<option value="<?php echo $page->ID; ?>" <?php selected( $rule['link'], $page->ID ); ?>>
											<?php echo esc_html( $page->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="nrfc_auto_links_settings[rules][<?php echo $index; ?>][target_pages][]" multiple style="height: 100px; width: 100%;">
									<?php foreach ( $pages as $page ) : ?>
										<option value="<?php echo $page->ID; ?>" <?php echo in_array( $page->ID, (array) $rule['target_pages'] ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $page->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'delete_rule', $index ), 'nrfc_delete_rule_action' ) ); ?>" class="button button-link-delete">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rules ) ) : ?>
						<tr>
							<td colspan="4">No rules defined yet. Click "Add Rule" to start.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<form method="post" style="display: inline-block;">
			<?php wp_nonce_field( 'nrfc_auto_links_action', 'nrfc_auto_links_nonce' ); ?>
			<input type="submit" name="nrfc_add_rule" class="button" value="Add Rule" />
		</form>
	</div>
	<?php
}

/**
 * Automatically link text based on settings.
 *
 * @param string $content Post content.
 * @return string
 */
function nrfc_auto_links_filter_content( $content ) {
	if ( empty( $content ) ) {
		return $content;
	}

	$settings = get_option( 'nrfc_auto_links_settings', [] );
	$rules    = NRFC_Auto_Links_Rule_Engine::rules_from_settings( $settings );

	$current_page_id = get_the_ID();

	return NRFC_Auto_Links_Rule_Engine::apply_rules(
		$content,
		$rules,
		(int) $current_page_id,
		'get_permalink',
		'esc_url',
		'esc_html'
	);
}

add_filter( 'the_content', 'nrfc_auto_links_filter_content', 999 );
