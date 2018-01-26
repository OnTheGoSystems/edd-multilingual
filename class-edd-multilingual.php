<?php
/**
 * Main plugin class.
 */
class EDD_Multilingual {

	/**
	 * EDD_Multilingual constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Sanity check.
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ! defined( 'EDD_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'error_no_plugins' ) );

			return;
		}

		// WPML setup has to be finished.
		if ( ! apply_filters( 'wpml_setting', false, 'setup_complete' ) ) {
			add_action( 'admin_notices', array( $this, 'error_wpml_setup' ) );

			return;
		}

		$this->init_hooks();
		$this->switch_payment_language();
		$this->translate_page_ids();
	}

	/**
	 * Error message if requirements not met.
	 */
	public function error_no_plugins() {
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
	public function error_wpml_setup() {
		$message = __( '%s plugin is enabled but not effective. You have to finish WPML setup.', 'edd_multilingual' );
		echo '<div class="error"><p>' . sprintf( $message, '<strong>EDD multilingual</strong>' ) . '</p></div>';
	}

	/**
	 * Load plugin hooks.
	 */
	public function init_hooks() {
		global $wpdb, $sitepress;

		// Save order language.
		add_action( 'edd_insert_payment', array( $this, 'save_payment_language' ), 10 );

		// Add language column to the payments history table.
		add_filter( 'edd_payments_table_columns', array( $this, 'payments_table_language_column' ) );
		add_filter( 'edd_payments_table_column', array( $this, 'render_payments_table_column' ), 10, 3 );

		// Add back the flags to downloads manager. NOTE: Not working when EDD FES is used.
		add_filter( 'edd_download_columns', array(
			new WPML_Custom_Columns( $sitepress ),
			'add_posts_management_column'
		) );

		// Remove WPML header for some admin pages.
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array(
				'edd-payment-history',
				'edd-discounts',
				'edd-settings'
			) )
		) {
			add_action( 'init', array( $this, 'remove_wpml_language_filter' ) );
		}
	}

	/**
	 * Save the language the payment was made in.
	 *
	 * @param $payment
	 */
	public function save_payment_language( $payment ) {
		update_post_meta( $payment, 'wpml_language', apply_filters( 'wpml_current_language', null ) );
	}

	/**
	 * Send email notifications in the correct language.
	 */
	public function switch_payment_language() {
		if ( is_admin() && isset( $_GET['edd-action'] ) && $_GET['edd-action'] == 'email_links' ) {
			$language_code = self::get_payment_language( $_GET['purchase_id'] );

			if ( ! empty( $language_code ) ) {
				do_action( 'wpml_switch_language', $language_code );
			}
		}
	}

	/**
	 * Remove WPML post count per language in admin page header.
	 */
	public function remove_wpml_language_filter() {
		global $sitepress;

		remove_action( 'admin_enqueue_scripts', array( $sitepress, 'language_filter' ) );
	}

	/**
	 * Translate EDD page IDs.
	 */
	public function translate_page_ids() {
		global $edd_options;

		// Re-read settings because EDD reads them before WPML has hooked onto the filters.
		$edd_options = edd_get_settings();

		// Translate post_id for pages in options.
		isset( $edd_options['purchase_page'] ) ? $edd_options['purchase_page'] = apply_filters( 'wpml_object_id', $edd_options['purchase_page'], 'page', true ) : '';
		isset( $edd_options['success_page'] ) ? $edd_options['success_page']  = apply_filters( 'wpml_object_id', $edd_options['success_page'], 'page', true ) : '';
		isset( $edd_options['failure_page'] ) ? $edd_options['failure_page']  = apply_filters( 'wpml_object_id', $edd_options['failure_page'], 'page', true ) : '';
		isset( $edd_options['purchase_history_page'] ) ? $edd_options['purchase_history_page']  = apply_filters( 'wpml_object_id', $edd_options['purchase_history_page'], 'page', true ) : '';
		isset( $edd_options['login_redirect_page'] ) ? $edd_options['login_redirect_page']  = apply_filters( 'wpml_object_id', $edd_options['login_redirect_page'], 'page', true ) : '';

		// Translate post_id for edd-fes add-on.
		isset( $edd_options['fes-vendor-dashboard-page'] ) ? $edd_options['fes-vendor-dashboard-page'] = apply_filters( 'wpml_object_id', $edd_options['fes-vendor-dashboard-page'], 'page', true ) : '';
		isset( $edd_options['fes-vendor-page'] ) ? $edd_options['fes-vendor-page'] = apply_filters( 'wpml_object_id', $edd_options['fes-vendor-page'], 'page', true ) : '';
		isset( $edd_options['fes-submission-form'] ) ? $edd_options['fes-submission-form'] = apply_filters( 'wpml_object_id', $edd_options['fes-submission-form'], 'page', true ) : '';
		isset( $edd_options['fes-profile-form'] ) ? $edd_options['fes-profile-form'] = apply_filters( 'wpml_object_id', $edd_options['fes-profile-form'], 'page', true ) : '';
		isset( $edd_options['fes-login-form'] ) ? $edd_options['fes-login-form'] = apply_filters( 'wpml_object_id', $edd_options['fes-login-form'], 'page', true ) : '';
		isset( $edd_options['fes-registration-form'] ) ? $edd_options['fes-registration-form']  = apply_filters( 'wpml_object_id', $edd_options['fes-registration-form'], 'page', true ) : '';
		isset( $edd_options['fes-vendor-contact-form'] ) ? $edd_options['fes-vendor-contact-form'] = apply_filters( 'wpml_object_id', $edd_options['fes-vendor-contact-form'], 'page', true ) : '';
	}

	/**
	 * Add "Language" column to the payments table.
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function payments_table_language_column( $columns ) {
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
	public function render_payments_table_column( $value, $payment_id, $column_name ) {
		if ( $column_name === 'language' ) {
			$language_code = self::get_payment_language( $payment_id );
			$languages     = apply_filters( 'wpml_active_languages', null, 'orderby=id&order=desc' );

			if ( array_key_exists( $language_code, $languages ) ) {
				$language_data = $languages[ $language_code ];

				$value = '<img src="' . $language_data['country_flag_url'] . '" height="12" width="18" />';
				return $value;
			}

			$value = __( 'N/A', 'edd_multilingual' );
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
	public static function get_payment_language( $payment_id ) {
		return get_post_meta( intval( $payment_id ), 'wpml_language', true );
	}
}
