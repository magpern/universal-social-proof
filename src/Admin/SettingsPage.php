<?php
/**
 * Social Proof admin settings + diagnostics page.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Admin;

use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Template\TemplateSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders WooCommerce → Social Proof.
 */
final class SettingsPage {

	/**
	 * Render full admin page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Social Proof.', 'universal-social-proof' ), 403 );
		}

		SettingsRepository::maybe_migrate();
		$settings = SettingsRepository::get_persisted();
		self::render_notices();
		?>
		<div class="wrap usp-admin">
			<h1><?php echo esc_html__( 'Social Proof', 'universal-social-proof' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Configure genuine WooCommerce purchase notifications. Administrators cannot create fabricated purchases.', 'universal-social-proof' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="usp-settings-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( SettingsSaveHandler::ACTION_SAVE ); ?>" />
				<?php wp_nonce_field( SettingsSaveHandler::NONCE_SAVE ); ?>

				<h2><?php echo esc_html__( 'General', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="usp_display_enabled"><?php echo esc_html__( 'Display notifications', 'universal-social-proof' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="usp_display_enabled" id="usp_display_enabled" value="1" <?php checked( ! empty( $settings['display_enabled'] ) ); ?> />
								<?php echo esc_html__( 'Show storefront toaster notifications', 'universal-social-proof' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'When disabled, the toaster is not shown and the notifications API returns an empty list. Purchase capture, privacy, and retention continue.', 'universal-social-proof' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Display', 'universal-social-proof' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Presentation timing (delays, gaps, motion) is not operator-configurable in v1.', 'universal-social-proof' ); ?>
				</p>

				<h2><?php echo esc_html__( 'Content / Templates', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="usp_template"><?php echo esc_html__( 'Notification template', 'universal-social-proof' ); ?></label>
						</th>
						<td>
							<textarea name="usp_template" id="usp_template" class="large-text code" rows="3" maxlength="<?php echo esc_attr( (string) TemplateSettings::MAX_LENGTH ); ?>"><?php echo esc_textarea( (string) $settings['template'] ); ?></textarea>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: token list */
										__( 'Allowed tokens: %s. No HTML. Default: Someone purchased {{product}}', 'universal-social-proof' ),
										'{{product}}, {{country}}, {{location}}, {{time_ago}}, {{quantity}}'
									)
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Products', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="usp_excluded_product_ids"><?php echo esc_html__( 'Excluded products', 'universal-social-proof' ); ?></label>
						</th>
						<td>
							<select
								class="wc-product-search"
								multiple="multiple"
								style="width: 50%;"
								id="usp_excluded_product_ids"
								name="usp_excluded_product_ids[]"
								data-placeholder="<?php echo esc_attr__( 'Search for a product…', 'universal-social-proof' ); ?>"
								data-action="woocommerce_json_search_products_and_variations"
							>
								<?php
								$ids = is_array( $settings['excluded_product_ids'] ) ? $settings['excluded_product_ids'] : array();
								foreach ( $ids as $product_id ) {
									$product_id = (int) $product_id;
									if ( $product_id <= 0 ) {
										continue;
									}
									$label = '#' . $product_id;
									if ( function_exists( 'wc_get_product' ) ) {
										$product = wc_get_product( $product_id );
										if ( $product ) {
											$label = $product->get_formatted_name();
										}
									}
									echo '<option value="' . esc_attr( (string) $product_id ) . '" selected="selected">' . esc_html( $label ) . '</option>';
								}
								?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Maximum 200 IDs. Server validates all submitted IDs. Missing/deleted products stay listed until removed.', 'universal-social-proof' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Out of stock', 'universal-social-proof' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="usp_exclude_out_of_stock" id="usp_exclude_out_of_stock" value="1" <?php checked( ! empty( $settings['exclude_out_of_stock'] ) ); ?> />
								<?php echo esc_html__( 'Exclude out-of-stock products from notifications', 'universal-social-proof' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Targeting', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Cart', 'universal-social-proof' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="usp_load_on_cart" id="usp_load_on_cart" value="1" <?php checked( ! empty( $settings['load_on_cart'] ) ); ?> />
								<?php echo esc_html__( 'Show notifications on the cart page', 'universal-social-proof' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Account', 'universal-social-proof' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="usp_load_on_account" id="usp_load_on_account" value="1" <?php checked( ! empty( $settings['load_on_account'] ) ); ?> />
								<?php echo esc_html__( 'Show notifications on My Account pages', 'universal-social-proof' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Checkout', 'universal-social-proof' ); ?></th>
						<td>
							<p><strong><?php echo esc_html__( 'Hard denied', 'universal-social-proof' ); ?></strong> — <?php echo esc_html__( 'Notifications never load on checkout.', 'universal-social-proof' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Geography', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Visitor-country weighting', 'universal-social-proof' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="usp_geo_weighting_enabled" id="usp_geo_weighting_enabled" value="1" <?php checked( ! empty( $settings['geo_weighting_enabled'] ) ); ?> />
								<?php echo esc_html__( 'Prefer purchases matching the visitor’s country when Universal Geo Context is available', 'universal-social-proof' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Visitor country comes from Universal Geo Context. USP does not perform IP geolocation. Visitor country is not stored. Purchase country comes from the WooCommerce order. If UGC is unavailable, selection falls back to global.', 'universal-social-proof' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Privacy / Retention', 'universal-social-proof' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="usp_retention_days"><?php echo esc_html__( 'Retention days', 'universal-social-proof' ); ?></label>
						</th>
						<td>
							<input type="number" name="usp_retention_days" id="usp_retention_days" min="<?php echo esc_attr( (string) RetentionSettings::MIN ); ?>" max="<?php echo esc_attr( (string) RetentionSettings::MAX ); ?>" value="<?php echo esc_attr( (string) (int) $settings['retention_days'] ); ?>" class="small-text" />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: min, 2: max, 3: default */
										__( 'Keep purchase events for %1$d–%2$d days (default %3$d). Changes apply on the next scheduled cleanup — not an immediate full purge.', 'universal-social-proof' ),
										RetentionSettings::MIN,
										RetentionSettings::MAX,
										RetentionSettings::DEFAULT
									)
								);
								?>
							</p>
							<p class="description">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: tools URL */
										__( 'Personal data export/erasure uses WordPress privacy tools. Open %s.', 'universal-social-proof' ),
										'<a href="' . esc_url( admin_url( 'export-personal-data.php' ) ) . '">' . esc_html__( 'Tools → Export Personal Data', 'universal-social-proof' ) . '</a>'
									),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save changes', 'universal-social-proof' ) ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Reset to defaults', 'universal-social-proof' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Reset all Social Proof settings to defaults? Events are not deleted.', 'universal-social-proof' ) ); ?>');">
				<input type="hidden" name="action" value="<?php echo esc_attr( SettingsSaveHandler::ACTION_RESET ); ?>" />
				<?php wp_nonce_field( SettingsSaveHandler::NONCE_RESET ); ?>
				<label>
					<input type="checkbox" name="usp_confirm_reset" value="1" required />
					<?php echo esc_html__( 'I understand this resets settings only and does not delete captured events.', 'universal-social-proof' ); ?>
				</label>
				<?php submit_button( __( 'Reset settings', 'universal-social-proof' ), 'secondary', 'submit', true ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Diagnostics', 'universal-social-proof' ); ?></h2>
			<?php self::render_diagnostics(); ?>
		</div>
		<?php
	}

	/**
	 * Diagnostics table (USP admin only).
	 */
	private static function render_diagnostics(): void {
		$d       = DiagnosticsService::collect();
		$events  = is_array( $d['events'] ?? null ) ? $d['events'] : array();
		$wc      = is_array( $d['woocommerce'] ?? null ) ? $d['woocommerce'] : array();
		$ugc     = is_array( $d['ugc'] ?? null ) ? $d['ugc'] : array();
		$cleanup = is_array( $d['cleanup'] ?? null ) ? $d['cleanup'] : array();
		$hpos    = is_array( $d['hpos'] ?? null ) ? $d['hpos'] : array();
		$eff     = is_array( $d['effective_settings'] ?? null ) ? $d['effective_settings'] : array();
		?>
		<table class="widefat striped usp-diagnostics" role="table">
			<tbody>
				<tr><th scope="row"><?php echo esc_html__( 'USP version', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) $d['runtime_version'] ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'DB_VERSION', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) $d['db_version'] ); ?> (<?php echo esc_html__( 'installed', 'universal-social-proof' ); ?>: <?php echo esc_html( (string) $d['installed_db'] ); ?>)</td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Settings version', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) (int) $d['settings_version'] ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'WooCommerce', 'universal-social-proof' ); ?></th><td><?php echo ! empty( $wc['active'] ) ? esc_html__( 'active', 'universal-social-proof' ) : esc_html__( 'inactive', 'universal-social-proof' ); ?> <?php echo esc_html( (string) ( $wc['version'] ?? '' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'HPOS', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $hpos['status'] ?? 'unknown' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'UGC', 'universal-social-proof' ); ?></th><td><?php echo ! empty( $ugc['available'] ) ? esc_html__( 'available', 'universal-social-proof' ) : esc_html__( 'unavailable', 'universal-social-proof' ); ?> <?php echo esc_html( trim( (string) ( $ugc['version'] ?? '' ) . ' ' . (string) ( $ugc['api'] ?? '' ) ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Template valid', 'universal-social-proof' ); ?></th><td><?php echo ! empty( $d['template_valid'] ) ? esc_html__( 'yes', 'universal-social-proof' ) : esc_html__( 'no', 'universal-social-proof' ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Cleanup scheduler', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $cleanup['scheduler'] ?? '' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Active events', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $events['active_count'] ?? '—' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Suppressed events', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $events['suppressed_count'] ?? '—' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Newest active occurred_at', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $events['newest_active_occurred_at'] ?? '—' ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Oldest active occurred_at', 'universal-social-proof' ); ?></th><td><?php echo esc_html( (string) ( $events['oldest_active_occurred_at'] ?? '—' ) ); ?></td></tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Effective settings', 'universal-social-proof' ); ?></th>
					<td>
						<code>
							<?php
							$encoded = wp_json_encode( $eff );
							echo esc_html( false === $encoded ? '{}' : $encoded );
							?>
						</code>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="description"><?php echo esc_html__( 'Diagnostics never show order IDs, customer identity, or visitor IP.', 'universal-social-proof' ); ?></p>
		<?php
	}

	/**
	 * Admin notices from redirect query args.
	 */
	private static function render_notices(): void {
		if ( empty( $_GET['usp_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$notice = sanitize_key( wp_unslash( (string) $_GET['usp_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class  = 'notice notice-success is-dismissible';
		$text   = '';
		if ( 'saved' === $notice ) {
			$text = __( 'Settings saved.', 'universal-social-proof' );
		} elseif ( 'reset' === $notice ) {
			$text = __( 'Settings reset to defaults. Events were not deleted.', 'universal-social-proof' );
		} elseif ( 'error' === $notice ) {
			$class = 'notice notice-error is-dismissible';
			$text  = ! empty( $_GET['usp_msg'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['usp_msg'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
				: __( 'Settings could not be saved.', 'universal-social-proof' );
		}
		if ( '' === $text ) {
			return;
		}
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
	}
}
