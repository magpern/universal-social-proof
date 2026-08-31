<?php
/**
 * Empty storefront toaster shell (no event payload).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Frontend;

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
		?>
		<div id="usp-toaster-root" class="usp-toaster" hidden data-usp-toaster>
			<div class="usp-toaster__panel" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true">
				<a class="usp-toaster__link" href="#" hidden>
					<span class="usp-toaster__media"></span>
					<span class="usp-toaster__body">
						<span class="usp-toaster__message" hidden></span>
						<time class="usp-toaster__time" datetime=""></time>
					</span>
				</a>
				<button type="button" class="usp-toaster__dismiss" hidden></button>
			</div>
		</div>
		<?php
	}
}
