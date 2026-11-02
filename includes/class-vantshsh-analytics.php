<?php
/**
 * Analytics and statistics tracking for shared carts.
 *
 * @package Vantigo_Share_Shop_Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VANTSHSH_Analytics {

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    /**
     * Get aggregate statistics summary.
     *
     * @return array
     */
    public static function get_stats_summary() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

        if ( ! $table_exists ) {
            return array(
                'total_shared'    => 0,
                'total_views'     => 0,
                'total_restored'  => 0,
                'total_value'     => 0.00,
                'conversion_rate' => 0,
            );
        }

        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT
            COUNT(id) as total_shared,
            COALESCE(SUM(views_count), 0) as total_views,
            COALESCE(SUM(restored_count), 0) as total_restored,
            COALESCE(SUM(cart_total), 0.00) as total_value
            FROM `" . esc_sql( $table_name ) . "`"
        );

        $total_shared   = $row ? (int) $row->total_shared : 0;
        $total_views    = $row ? (int) $row->total_views : 0;
        $total_restored = $row ? (int) $row->total_restored : 0;
        $total_value    = $row ? (float) $row->total_value : 0.00;

        $conversion_rate = 0;
        if ( $total_views > 0 ) {
            $conversion_rate = round( ( $total_restored / $total_views ) * 100, 1 );
        }

        return array(
            'total_shared'    => $total_shared,
            'total_views'     => $total_views,
            'total_restored'  => $total_restored,
            'total_value'     => $total_value,
            'conversion_rate' => $conversion_rate,
        );
    }

    /**
     * Get recent shared carts log.
     *
     * @param int $limit Max rows to fetch.
     * @return array
     */
    public static function get_recent_shared_carts( $limit = 10 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        if ( ! $table_exists ) {
            return array();
        }

        $limit = absint( $limit );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, cart_hash, created_at, expires_at, share_count, views_count, restored_count, cart_total FROM `" . esc_sql( $table_name ) . "` ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Clear all analytics data.
     */
    public static function reset_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vantshsh_shared_carts';

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        if ( $table_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "TRUNCATE TABLE `" . esc_sql( $table_name ) . "`" );
        }
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
