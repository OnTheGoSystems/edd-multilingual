<?php

/**
 * Main plugin class.
 */
class EDD_multilingual {

	/**
	 * EDD_multilingual constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'edd_ml_init' ), 20 );
	}

	/**
	 * Initialize plugin.
	 */
	public function edd_ml_init() {
		// Sanity check
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ! defined( 'EDD_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'edd_ml_error_no_plugins' ) );

			return;
		}

		// WPML setup has to be finished
		if ( ! apply_filters( 'wpml_setting', false, 'setup_complete' ) ) {
			add_action( 'admin_notices', array( $this, 'edd_ml_error_wpml_setup' ) );

			return;
		}

		$this->edd_ml_init_hooks();
		$this->edd_ml_switch_payment_language();
		$this->edd_ml_translate_page_ids();
	}

	/**
	 * Error message if requirements not met.
	 */
	public function edd_ml_error_no_plugins() {
		$message = __( '%s plugin is enabled but not effective. It requires %s and %s plugins in order to work.', 'edd_multilingual' );
		echo '<div class="error"><p>' .
			 sprintf( $message, '<strong>EDD multilingual</strong>',
				 '<a href="http://wpml.org/">WPML</a>',
				 '<a href="https://wordpress.org/plugins/easy-digital-downloads/">Easy Digital Downloads</a>' ) .
			 '</p></div>';
	}

	/**
	 * Error message if WPML setup is not finished.
	 */
	public function edd_ml_error_wpml_setup() {
		$message = __( '%s plugin is enabled but not effective. You have to finish WPML setup.', 'edd_multilingual' );
		echo '<div class="error"><p>' . sprintf( $message, '<strong>EDD multilingual</strong>' ) . '</p></div>';
	}

	/**
	 * Load plugin hooks.
	 */
	public function edd_ml_init_hooks() {
		global $wpdb, $sitepress;

		// Save order language.
		add_action( 'edd_insert_payment', array( $this, 'edd_ml_save_payment_language' ), 10 );

		// Add language column to the payments history table.
		add_filter( 'edd_payments_table_columns', array( $this, 'edd_ml_payments_table_language_column' ) );
		add_filter( 'edd_payments_table_column', array( $this, 'edd_ml_render_payments_table_column' ), 10, 3 );

		// Synchronize sales and earnings between translations.
		add_filter( 'update_post_metadata', array( $this, 'edd_ml_synchronize_download_totals' ), 10, 5 );
		add_action( 'edd_tools_recount_stats_after', array( $this, 'edd_ml_recount_stats' ) );
		add_action( 'wp_ajax_edd_recalculate', array( $this, 'edd_ml_recalculate_download_totals' ) );

		// Add back the flags to downloads manager.
		add_filter( 'edd_download_columns', array(
			new WPML_Custom_Columns( $wpdb, $sitepress ),
			'add_posts_management_column'
		) );

		// Remove WPML header for some admin pages.
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array(
				'edd-payment-history',
				'edd-discounts',
				'edd-settings'
			) )
		) {
			add_action( 'init', array( $this, 'edd_ml_remove_wpml_language_filter' ) );
		}
	}

	/**
	 * Save the language the payment was made in.
	 *
	 * @param $payment
	 */
	public function edd_ml_save_payment_language( $payment ) {
		update_post_meta( $payment, 'wpml_language', apply_filters( 'wpml_current_language', null ) );
	}

	/**
	 * Send email notifications in the correct language.
	 */
	public function edd_ml_switch_payment_language() {
		if ( is_admin() && isset( $_GET['edd-action'] ) && $_GET['edd-action'] == 'email_links' ) {
			$language_code = self::edd_ml_get_payment_language( $_GET['purchase_id'] );

			if ( ! empty( $language_code ) ) {
				do_action( 'wpml_switch_language', $language_code );
			}
		}
	}

	/**
	 * Remove WPML post count per language in admin page header.
	 */
	public function edd_ml_remove_wpml_language_filter() {
		global $sitepress;

		remove_action( 'admin_enqueue_scripts', array( $sitepress, 'language_filter' ) );
	}

	/**
	 *
	 * Synchronize download totals
	 *
	 * @param $null
	 * @param $object_id
	 * @param $meta_key
	 * @param $meta_value
	 * @param $prev_value
	 *
	 * @return null
	 */
	public function edd_ml_synchronize_download_totals( $null, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( in_array( $meta_key, array( '_edd_download_sales', '_edd_download_earnings' ) ) ) {
			remove_filter( 'update_post_metadata', array( $this, 'synchronize_download_totals' ), 10, 5 );
			$languages = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );

			foreach ( $languages as $lang ) {
				if ( $lang['language_code'] != apply_filters( 'wpml_current_language', null ) ) {
					$post_id = apply_filters( 'wpml_object_id', $object_id, 'download', false, $lang['language_code'] );
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}

			add_filter( 'update_post_metadata', array( $this, 'synchronize_download_totals' ), 10, 5 );
		}

		return null;
	}

	/**
	 * Display multilingual recount option in EDD > Tools
	 */
	public function edd_ml_recount_stats() {
		?>
		<div class="postbox">
			<h3><span><?php _e( 'EDD Multilingual - Recount Stats', 'easy-digital-downloads' ); ?></span></h3>
			<div class="inside recount-stats-controls">
				<div id="edd_ml_recalculate">
					<a class="button" href="#recalculate">Recalculate totals</a>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			jQuery(function ($) {
				var recalculate = $('#edd_ml_recalculate');

				recalculate.find('a').click(function () {
					var notice = $('.notice-wrap');

					recalculate.find('a').attr('disabled', 'disabled');
					$.post(
						ajaxurl, {
							action: "edd_recalculate"
						}, function (response) {
							recalculate.find('a').removeAttr('disabled');
							notice.remove();
							recalculate.append(
								'<div class="notice-wrap"><div id="edd-batch-success" class="updated notice is-dismissible"><p>' +
								response +
								'<span class="notice-dismiss"></span></p></div></div>'
							);
							$('.notice-dismiss').click(function () {
								notice.slideUp('slow', function () {
									this.remove();
								});
							})
						});
				});
			});
		</script>
		<?php
	}

	/**
	 * Recalculate download totals from EED > Reports
	 */
	public function edd_ml_recalculate_download_totals() {
		global $wpdb;

		$wpdb->query( "UPDATE $wpdb->postmeta 
					   SET meta_value = '0' 
					   WHERE meta_key 
					   IN ('_edd_download_earnings', '_edd_download_sales')" );
		$logs = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type = 'edd_log'" );

		foreach ( $logs as $log ) {
			$payment_id = get_post_meta( $log->ID, '_edd_log_payment_id', true );
			$language   = get_post_meta( $payment_id, 'wpml_language', true );

			if ( empty( $language ) ) {
				$language = apply_filters( 'wpml_default_language', null );
			}

			if ( get_post( $payment_id ) ) {
				$cart_items = edd_get_payment_meta_cart_details( $payment_id );

				if ( is_array( $cart_items ) ) {
					foreach ( $cart_items as $item ) {
						do_action( 'wpml_switch_language', $language );

						edd_increase_earnings( $item['id'], $item['price'] );
						edd_increase_purchase_count( $item['id'] );

						do_action( 'wpml_switch_language', null );
					}
				}
			}
		}
		// Delete all transients so that stats can be refreshed.
		$wpdb->query( "DELETE FROM $wpdb->options 
					   WHERE option_name 
					   LIKE '%edd_stats_%'
					   OR option_name LIKE '%edd_estimated_monthly_stats%'" );

		die( 'Successfully recalculated totals!' );
	}

    /**
     * Translate EDD page IDs.
     */
    public function edd_ml_translate_page_ids() {
        global $edd_options;

        // Re-read settings because EDD reads them before WPML has hooked onto the filters
        $edd_options = edd_get_settings();

        // Translate post_id for pages in options
        $edd_options['purchase_page'] = apply_filters( 'wpml_object_id', $edd_options['purchase_page'], 'page', true );
        $edd_options['success_page']  = apply_filters( 'wpml_object_id', $edd_options['success_page'], 'page', true );
        $edd_options['failure_page']  = apply_filters( 'wpml_object_id', $edd_options['failure_page'], 'page', true );
    }

	/**
	 * Add "Language" column to the payments table.
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function edd_ml_payments_table_language_column( $columns ) {
		$columns['language'] = __( 'Language', 'easy-digital-downloads' );

		return $columns;
	}

	/**
	 * Fill payments table "Language" column with payment languages presented as flags.
	 *
	 * @param $value
	 * @param $payment_id
	 * @param $column_name
	 *
	 * @return string
	 */
	public function edd_ml_render_payments_table_column( $value, $payment_id, $column_name ) {
		if ( $column_name === 'language' ) {
			$language_code = self::edd_ml_get_payment_language( $payment_id );
			$languages     = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );

			if ( array_key_exists( $language_code, $languages ) ) {
				$language_data = $languages[ $language_code ];

				$value = '<img src="' . $language_data['country_flag_url'] . '" height="12" width="18" />';
			} else {
				$value = __( 'N/A', 'edd_multilingual' );
			}
		}

		return $value;
	}

	/**
	 * Retrieving payment language from post_meta table.
	 *
	 * @param $payment_id
	 *
	 * @return String
	 */
	public static function edd_ml_get_payment_language( $payment_id ) {
		return get_post_meta( intval( $payment_id ), 'wpml_language', true );
	}
}