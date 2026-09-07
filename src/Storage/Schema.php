<?php
/**
 * USP events table DDL.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Schema definitions for `{prefix}usp_events`.
 */
final class Schema {

	public const DB_VERSION = '20260907v11a';

	public const TABLE_SUFFIX = 'usp_events';

	/**
	 * Fully qualified events table name.
	 */
	public static function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * CREATE TABLE statements keyed by fully qualified table name.
	 *
	 * @return array<string, string> Table name => CREATE TABLE SQL.
	 */
	public static function table_definitions(): array {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = self::events_table();

		return array(
			$table => "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(16) NOT NULL DEFAULT 'purchase',
				source_order_id bigint(20) unsigned DEFAULT NULL,
				source_item_id bigint(20) unsigned DEFAULT NULL,
				status varchar(16) NOT NULL,
				suppress_reason varchar(32) DEFAULT NULL,
				public_id char(36) NOT NULL,
				product_id bigint(20) unsigned NOT NULL,
				variation_id bigint(20) unsigned DEFAULT NULL,
				quantity decimal(18,6) NOT NULL,
				country_code char(2) DEFAULT NULL,
				occurred_at datetime NOT NULL,
				captured_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_source (event_type, source_order_id, source_item_id),
				UNIQUE KEY public_id (public_id),
				KEY status_occurred (status, occurred_at),
				KEY status_type_occurred (status, event_type, occurred_at),
				KEY status_country_occurred (status, country_code, occurred_at),
				KEY status_product_occurred (status, product_id, occurred_at)
			) {$charset};",
		);
	}
}
