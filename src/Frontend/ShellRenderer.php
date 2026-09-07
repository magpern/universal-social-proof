<?php
/**
 * Empty storefront toaster shell (no event payload).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Frontend;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the inert HTML shell when assets are loaded.
 */
final class ShellRenderer {

	/**
	 * Print the empty toaster shell in the footer.
	 */
	public static function render(): void {
		if ( ! AssetLoader::was_enqueued() ) {
			return;
		}

		$appearance = SettingsRepository::appearance();
		$position   = (string) $appearance['position'];
		$shadow     = (string) $appearance['shadow'];
		$classes    = array(
			'usp-toaster',
			'usp-toaster--' . $position,
			'usp-toaster--shadow-' . $shadow,
		);
		if ( empty( $appearance['show_product_image'] ) ) {
			$classes[] = 'usp-toaster--hide-image';
		}
		if ( empty( $appearance['show_close'] ) ) {
			$classes[] = 'usp-toaster--hide-close';
		}

		$style = sprintf(
			'--usp-toast-background:%1$s;--usp-toast-text:%2$s;--usp-toast-accent:%3$s;--usp-toast-radius:%4$dpx;--usp-bg:var(--usp-toast-background);--usp-text:var(--usp-toast-text);--usp-accent:var(--usp-toast-accent);--usp-radius:var(--usp-toast-radius);',
			esc_attr( (string) $appearance['background'] ),
			esc_attr( (string) $appearance['text'] ),
			esc_attr( (string) $appearance['accent'] ),
			(int) $appearance['radius']
		);
		?>
		<div id="usp-toaster-root" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" hidden data-usp-toaster>
			<div class="usp-toaster__panel" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true">
				<a class="usp-toaster__link" href="#" hidden>
					<span class="usp-toaster__media"></span>
					<span class="usp-toaster__body">
						<span class="usp-toaster__message" hidden></span>
						<time class="usp-toaster__time" datetime=""></time>
					</span>
				</a>
				<button type="button" class="usp-toaster__dismiss" hidden aria-label="<?php echo esc_attr__( 'Dismiss notification', 'universal-social-proof' ); ?>"></button>
			</div>
		</div>
		<?php
	}
}
