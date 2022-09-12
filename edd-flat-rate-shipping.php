<?php
/**
 * Plugin Name: Easy Digital Downloads - Flat Rate Shipping
 * Plugin URI: https://github.com/jeremyers1/EDD-Flat-Rate-Shipping
 * Description: Provides the ability to charge a single flat-rate shipping fee for physical products in EDD.
 * Version: 1.0.0
 * Author: Jeremy D Myers
 * Author URI:  https://jeremydmyers.com
 * Contributors: easydigitaldownloads, mordauk, cklosows, jeremyers1
 * Text Domain: edd-flat-rate-shipping
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * for testing purposes 
 */ 
function console_log($data) {
	if (is_array($data))
			$data= implode(',', $data);

	echo "<script>console.log('Debug: " . $data . "' );</script>";
}

class EDD_Flat_Rate_Shipping {

	private static $instance;

	/**
	 * Flag for domestic / international shipping
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected $is_domestic = true;

	/**
	 * Flag for whether Frontend Submissions is enabled
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected $is_fes = false;

	public $plugin_path = null;
	public $plugin_url  = null;

	public $settings;
	public $metabox;
	public $admin;
	public $tracking;
	public $fes;

	/**
	 * Get active object instance
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @static
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new EDD_Flat_Rate_Shipping();
		}

		return self::$instance;
	}

	/**
	 * Initialise the rest of the plugin
	 */
	private function __construct() {

		// do nothing if EDD is not activated
		if( ! class_exists( 'Easy_Digital_Downloads', false ) ) {
			return;
		}

		$this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
		$this->plugin_url  = untrailingslashit( plugin_dir_url( __FILE__ ) );
		$this->setup_constants();
		$this->maybeRunInstall();
		$this->filters();
		$this->actions();
		$this->init();
	}

	private function setup_constants() {
		if ( ! defined( 'EDD_FLAT_RATE_SHIPPING_VERSION' ) ) {
			define( 'EDD_FLAT_RATE_SHIPPING_VERSION', '1.0.0' );
		}
	}

	/**
	 * Runs the installer if our flag is set.
	 *
	 * @since 1.0.0
	 */
	private function maybeRunInstall() {
		add_action( 'admin_init', function() {
			if ( get_option( 'edd_flat_rate_shipping_run_install' ) ) {
				edd_flat_rate_shipping_install();

				delete_option( 'edd_flat_rate_shipping_run_install' );
			}
		} );
	}

	public function filters() {
		add_filter( 'edd_purchase_data_before_gateway',    array( $this, 'set_shipping_info' ), 10, 2 );
		add_filter( 'edd_paypal_redirect_args',            array( $this, 'send_shipping_to_paypal' ), 10, 2 );
		add_filter( 'edd_paypal_order_arguments',          array( $this, 'add_shipping_to_paypal_commerce' ), 10, 3 );
		add_filter( 'edd_sale_notification',               array( $this, 'admin_sales_notice' ), 10, 3 );
		add_filter( 'edd_get_order_details_sections',      array( $this, 'add_shipping_details_section' ), 10, 2 );
	}

	public function actions() {
		add_action( 'init',                                  array( $this, 'apply_shipping_fees' ) );
		add_action( 'wp_ajax_edd_get_shipping_rate',         array( $this, 'ajax_shipping_rate' ) );
		add_action( 'wp_ajax_nopriv_edd_get_shipping_rate',  array( $this, 'ajax_shipping_rate' ) );
		add_action( 'edd_purchase_form_after_cc_form',       array( $this, 'address_fields' ), 999 );
		add_action( 'edd_checkout_error_checks',             array( $this, 'error_checks' ), 10, 2 );
		add_action( 'edd_view_order_details_billing_after',  array( $this, 'show_shipping_details' ), 10 );
		add_action( 'edd_insert_payment',                    array( $this, 'set_as_not_shipped' ), 10, 2 );
		add_action( 'edd_insert_payment',                    array( $this, 'add_shipping_address_to_order' ), 10, 2 );
		add_action( 'edd_edit_payment_bottom',               array( $this, 'edit_payment_option' ) );

		add_action( 'edd_profile_editor_address',            array( $this, 'profile_editor_addresses' ), 10 );
		add_action( 'edd_profile-remove-shipping-address',   array( $this, 'process_profile_editor_remove_address' ) );

		add_action( 'admin_enqueue_scripts',                 array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts',                    array( $this, 'enqueue_styles' ), 100 );
	}

	/**
	 * Run action and filter hooks.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		// Include the necessary files.
		require_once $this->plugin_path . '/includes/admin/settings.php';
		require_once $this->plugin_path . '/includes/tracking.php';
		require_once $this->plugin_path . '/includes/privacy-functions.php';
		require_once $this->plugin_path . '/includes/functions.php';

		if ( is_admin() ) {
			require_once $this->plugin_path . '/includes/admin/admin.php';
			require_once $this->plugin_path . '/includes/admin/metabox.php';
			require_once $this->plugin_path . '/includes/admin/upgrades.php';
		}

		// Load all the settings into local variables so we can use them.
		$this->settings = new EDD_Flat_Rate_Shipping_Settings();
		$this->tracking = new EDD_Flat_Rate_Shipping_Tracking();
		if ( is_admin() ) {
			$this->admin = new EDD_Flat_Rate_Shipping_Admin();
			$this->metabox = new EDD_Flat_Rate_Shipping_Metabox();
		}

		$this->plugins_check();
	}

	/**
	 * Register any scripts we need for Flat Rate Shipping
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_scripts() {
		wp_register_script( 'edd-flat-rate-shipping-admin', $this->plugin_url . '/assets/js/admin-scripts.js', array( 'jquery' ), EDD_FLAT_RATE_SHIPPING_VERSION );
		wp_enqueue_script( 'edd-flat-rate-shipping-admin' );

		wp_register_style( 'edd-flat-rate-shipping-admin', $this->plugin_url . '/assets/css/admin-styles.css', EDD_FLAT_RATE_SHIPPING_VERSION );
		wp_enqueue_style( 'edd-flat-rate-shipping-admin' );
	}

	/**
	 * Register any styles we need for Flat Rate Shipping
	 *
	 * @since 2.3
	 * @return void
	 */
	public function enqueue_styles() {
		wp_register_script( 'edd-flat-rate-shipping', $this->plugin_url . '/assets/js/index.js', array( 'edd-checkout-global' ), EDD_FLAT_RATE_SHIPPING_VERSION, true );
		if ( edd_is_checkout() && $this->needs_shipping_fields() ) {
			add_filter( 'edd_global_checkout_script_vars', function( $vars ) {
				$vars['shipping_base_region'] = edd_get_option( 'edd_flat_rate_shipping_base_country', 'US' );

				return $vars;
			} );
			wp_enqueue_script( 'edd-flat-rate-shipping' );
		}

		$needs_styles = edd_is_purchase_history_page();

		if ( false === $needs_styles ) {
			return;
		}

		wp_register_style( 'edd-flat-rate-shipping-styles', $this->plugin_url . '/assets/css/styles.css', EDD_FLAT_RATE_SHIPPING_VERSION );
		wp_enqueue_style( 'edd-flat-rate-shipping-styles' );
	}

	/**
	 * Determine if dependent plugins are loaded and set flags appropriately
	 *
	 * @since 2.0
	 *
	 * @access private
	 * @return void
	 */
	public function plugins_check() {

		if( class_exists( 'EDD_Front_End_Submissions' ) ) {
			$this->is_fes = true;
			require_once $this->plugin_path . '/includes/integrations/edd-fes.php';
			$this->fes = new EDD_Flat_Rate_Shipping_FES();


			if ( ! isset( $this->admin ) ) {
				require_once $this->plugin_path . '/includes/admin/admin.php';
				$this->admin = new EDD_Flat_Rate_Shipping_Admin();
			}
			add_action( 'fes-order-table-column-title', array( $this->admin, 'shipped_column_header' ), 10 );
			add_action( 'fes-order-table-column-value', array( $this->admin, 'shipped_column_value' ), 10 );

			add_action( 'edd_payment_receipt_after',    array( $this, 'payment_receipt_after' ), 10, 2 );
			add_action( 'edd_toggle_shipped_status',    array( $this, 'frontend_toggle_shipped_status' ) );

			// FES 2.3+ compatibility
			add_action( 'fes_load_fields_require',  array( $this->fes, 'edd_fes_flat_rate_shipping' ) );

			// FES < 2.3 compatibility
			if ( defined( 'fes_plugin_version' ) && version_compare( fes_plugin_version, '2.3', '<' ) ) {
				add_action( 'fes_custom_post_button',               array( $this->fes, 'edd_fes_flat_rate_shipping_field_button' ) );
				add_action( 'fes_admin_field_edd_flat_rate_shipping',  array( $this->fes, 'edd_fes_flat_rate_shipping_admin_field' ), 10, 3 );
				add_filter( 'fes_formbuilder_custom_field',         array( $this->fes, 'edd_fes_flat_rate_shipping_formbuilder_is_custom_field' ), 10, 2 );
				add_action( 'fes_submit_submission_form_bottom',    array( $this->fes, 'edd_fes_flat_rate_shipping_save_custom_fields' ) );
				add_action( 'fes_render_field_edd_flat_rate_shipping', array( $this->fes, 'edd_fes_flat_rate_shipping_field' ), 10, 3 );
			}
		}

	}

	/**
	 * Determine if a product has snipping enabled
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 * @return bool
	 */
	protected function item_has_shipping( $item_id = 0 ) {
		$enabled = get_post_meta( $item_id, '_edd_enable_shipping', true );

		return (bool) apply_filters( 'edd_flat_rate_shipping_item_has_shipping', $enabled, $item_id );
	}


	/**
	 * Determine if a price option has snipping enabled
	 *
	 * @since 1.0
	 *
	 * @access protected
	 * @return bool
	 */
	protected function price_has_shipping( $item_id = 0, $price_id = 0 ) {
		$prices = edd_get_variable_prices( $item_id );
		$ret    = false;

		// Backwards compatibility checks
		$has_shipping = isset( $prices[ $price_id ]['shipping'] ) ? $prices[ $price_id ]['shipping'] : false;
		if ( false !== $has_shipping && ! is_array( $has_shipping ) ) {
			$ret = true;
		} elseif ( is_array( $has_shipping ) ) {
			$domestic = $has_shipping['domestic'];
			$international = $has_shipping['international'];

			// If the price has either domestic or international prices, we have shipping.
			$ret = ( ! empty( $domestic ) || ! empty( $international ) ) ? true : false;
		}

		// Keep this old filter for backwards compatibility.
		$ret = apply_filters( 'edd_flat_rate_shipping_price_hasa_shipping', $ret, $item_id, $price_id );

		return (bool) apply_filters( 'edd_flat_rate_shipping_price_has_shipping', $ret, $item_id, $price_id );
	}

	/**
	 * Get the shipping cost for a specific download and/or price ID.
	 *
	 * @since 2.2.3
	 * @param int    $download_id The Download ID to look up.
	 * @param null   $price_id    The Price ID to look up.
	 * @param string $region      The region to pull for (domestic or international).
	 *
	 * @return float
	 */
	public function get_price_shipping_cost( $download_id = 0, $price_id = null, $region = 'domestic' ) {
		$amount = 0;

		if ( ! is_numeric( $price_id ) ) {
			$amount = get_post_meta( $download_id, "_edd_shipping_{$region}", true );
		} else {
			$download = new EDD_Download( $download_id );
			if ( $download->has_variable_prices() ) {
				$prices = $download->get_prices();
				foreach ( $prices as $key => $price ) {

					// If it's not the right price ID, move along.
					if ( (int) $key !== (int) $price_id ) {
						continue;
					}

					if ( isset( $price['shipping'] ) && is_array( $price['shipping'] ) ) {
						// If the region requested isn't set, continue;
						if ( ! isset( $price['shipping'][ $region ] ) ) {
							continue;
						}

						$amount = $price['shipping'][ $region ];
					} elseif ( isset( $price['shipping'] ) ) {
						$amount = get_post_meta( $download_id, "_edd_shipping_{$region}", true );
					}
				}
			}
		}

		return apply_filters( 'edd_shipping_variable_price_cost', (float) $amount, $download_id, $price_id, $region );
	}

	/**
	 * Determine if shipping costs need to be calculated for the cart
	 *
	 * @since 1.0
	 *
	 * @access protected
	 * @return bool
	 */
	protected function cart_needs_shipping() {
		$cart_contents = edd_get_cart_contents();
		$ret = false;
		if( is_array( $cart_contents ) ) {
			foreach( $cart_contents as $item ) {
				$price_id = isset( $item['options']['price_id'] ) ? (int) $item['options']['price_id'] : null;
				if( $this->item_has_shipping( $item['id'], $price_id ) ) {
					$ret = true;
					break;
				}
			}
		}
		return (bool) apply_filters( 'edd_flat_rate_shipping_cart_needs_shipping', $ret );
	}


	/**
	 * Get the base country (where the store is located)
	 *
	 * This is used for determining if customer should be charged domestic or international shipping
	 *
	 * @since 1.0
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_base_region( $download_id = 0 ) {

		$base_region = edd_get_option( 'edd_flat_rate_shipping_base_country' );
		if ( empty( $base_region ) ) {
			$base_region = edd_get_option( 'base_country' );
		}
		if ( empty( $download_id ) ) {
			return $base_region;
		}

		$author  = get_post_field( 'post_author', $download_id );
		$country = get_user_meta( $author, 'vendor_country', true );
		if ( $country ) {
			$countries = edd_get_country_list();
			$code      = array_search( $country, $countries );
			if ( false !== $code ) {
				$base_region = $code;
			}
		}

		return $base_region;
	}

	/**
	 * Update the shipping costs via ajax
	 *
	 * This fires when the customer changes the country they are shipping to
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	 */
	public function ajax_shipping_rate() {

		// Calculate new shipping
		$this->apply_shipping_fees();

		ob_start();
		edd_checkout_cart();
		$cart = ob_get_clean();

		wp_send_json_success(
			array(
				'html'  => $cart,
				'total' => html_entity_decode( edd_cart_total( false ), ENT_COMPAT, 'UTF-8' ),
			)
		);
	}


	/**
	 * Apply the shipping fees to the cart
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @return void
	 */
	public function apply_shipping_fees() {

		$this->remove_shipping_fees();

		if ( ! $this->cart_needs_shipping() ) {
			return;
		}

		$cart_contents = edd_get_cart_content_details();

		if ( ! is_array( $cart_contents ) ) {
			return;
		}

		$country = $this->get_shipping_country();
		if ( ! empty( $country ) && $country !== $this->get_base_region() ) {
			$this->is_domestic = false;
		}

		$amount = 0.00;
		foreach ( $cart_contents as $key => $item ) {

			$price_id = isset( $item['item_number']['options']['price_id'] ) ? (int) $item['item_number']['options']['price_id'] : null;

			if ( ! $this->item_has_shipping( $item['id'], $price_id ) ) {
				continue;
			}

			if ( $this->is_fes && $country !== $this->get_base_region( $item['id'] ) ) {
				$this->is_domestic = false;
			}

			$has_shipping = false;
			$fee_label    = __( 'Shipping', 'edd-flat-rate-shipping' );
			if ( ! function_exists( 'edd_get_order_address' ) ) {
				/* translators: the product name */
				$fee_label = sprintf( __( '%s Shipping', 'edd-flat-rate-shipping' ), get_the_title( $item['id'] ) );
			}
			if ( ! empty( $item['fees'] ) ) {
				foreach ( $item['fees'] as $fee ) {
					if ( $fee['label'] === $fee_label ) {
						$has_shipping = true;
						break;
					}
				}
			}

			$region = $this->is_domestic ? 'domestic' : 'international';
			$amount = $this->get_price_shipping_cost( $item['id'], $price_id, $region );
			if ( $amount > 0 && false === $has_shipping ) {

				$id = "flat_rate_shipping_{$item['id']}";
				if ( null !== $price_id ) {
					$id .= "_{$price_id}";
				}
				EDD()->fees->add_fee( array(
					'amount'      => $amount,
					'label'       => $fee_label,
					'id'          => $id,
					'download_id' => $item['id'],
					'price_id'    => $price_id,
					'no_tax'      => edd_get_option( 'flat_rate_shipping_disable_tax_on_shipping', false ),
				) );
			}
		}
	}

	/**
	 * Gets the shipping country: first from AJAX, then stored in user account,
	 * then base shipping country as fallback.
	 *
	 * @since 2.4
	 * @return string
	 */
	private function get_shipping_country() {
		$country = filter_input( INPUT_POST, 'country', FILTER_SANITIZE_STRING );
		if ( ! empty( $country ) ) {
			return $country;
		}

		/**
		 * Stripe compatibility.
		 * @see _edds_process_purchase_form()
		 */
		if ( ! empty( $_POST['form_data'] ) ) {
			parse_str( $_POST['form_data'], $form_data );
			if ( ! empty( $form_data['edd_shipping_country'] ) ) {
				return $form_data['edd_shipping_country'];
			}
		}

		if ( is_user_logged_in() ) {
			$address = edd_get_customer_address( get_current_user_id() );
			if ( ! empty( $address['country'] ) ) {
				return $address['country'];
			}
		}

		return $this->get_base_region();
	}

	/**
	 * Removes all shipping fees from the cart
	 *
	 * @since 2.1
	 *
	 * @access public
	 * @return void
	 */
	public function remove_shipping_fees() {

		$fees = EDD()->fees->get_fees( 'fee' );
		if( empty( $fees ) ) {
			return;
		}

		foreach( $fees as $key => $fee ) {

			if( false === strpos( $key, 'flat_rate_shipping' ) ) {
				continue;
			}

			unset( $fees[ $key ] );

		}

		EDD()->session->set( 'edd_cart_fees', $fees );

	}


	/**
	 * Determine if the shipping fields should be displayed
	 *
	 * @since 1.0
	 *
	 * @access protected
	 * @return bool
	 */
	protected function needs_shipping_fields() {
		return $this->cart_needs_shipping();

	}


	/**
	 * Determine if the current payment method has billing fields
	 *
	 * If no billing fields are present, the shipping fields are always displayed
	 *
	 * @since 1.0
	 *
	 * @access protected
	 * @return bool
	 */
	protected function has_billing_fields() {

		$did_action = did_action( 'edd_after_cc_fields', 'edd_default_cc_address_fields' );
		if ( ! $did_action && edd_use_taxes() ) {
			$did_action = did_action( 'edd_purchase_form_after_cc_form', 'edd_checkout_tax_fields' );
		}

		// Have to assume all gateways are using the default CC fields (they should be)
		return ( $did_action || isset( $_POST['card_address'] ) );
	}

	/**
	 * Shipping info fields
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function address_fields() {

		if ( ! $this->needs_shipping_fields() ) {
			return;
		}

		include 'includes/views/checkout-address-fields.php';
	}

	/**
	 * Perform error checks during checkout
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function error_checks( $valid_data, $post_data ) {

		// Only perform error checks if we have a product that needs shipping
		if ( ! $this->cart_needs_shipping() ) {
			return;
		}

		// Check to see if shipping is different than billing
		if ( isset( $post_data['edd_use_different_shipping'] ) || ! $this->has_billing_fields() ) {

			if ( ! isset( $post_data['existing_shipping_address'] ) || in_array( $post_data['existing_shipping_address'], array( '-1', 'new' ), true ) ) {

				// Shipping address is different

				if ( empty( $post_data[ 'shipping_address' ] ) ) {
					edd_set_error( 'missing_address', __( 'Please enter a shipping address.', 'edd-flat-rate-shipping' ) );
				}

				if ( empty( $post_data[ 'shipping_city' ] ) ) {
					edd_set_error( 'missing_city', __( 'Please enter a city for shipping.', 'edd-flat-rate-shipping' ) );
				}

				if ( empty( $post_data[ 'shipping_zip' ] ) ) {
					edd_set_error( 'missing_zip', __( 'Please enter a zip/postal code for shipping.', 'edd-flat-rate-shipping' ) );
				}

				if ( empty( $post_data[ 'shipping_country' ] ) ) {
					edd_set_error( 'missing_country', __( 'Please select your country.', 'edd-flat-rate-shipping' ) );
				}

				if ( 'US' == $post_data[ 'shipping_country' ] ) {

					if ( empty( $post_data[ 'shipping_state_us' ] ) ) {
						edd_set_error( 'missing_state', __( 'Please select your state.', 'edd-flat-rate-shipping' ) );
					}

				} elseif ( 'CA' == $post_data[ 'shipping_country' ] ) {

					if ( empty( $post_data[ 'shipping_state_ca' ] ) ) {
						edd_set_error( 'missing_province', __( 'Please select your province.', 'edd-flat-rate-shipping' ) );
					}

				}

			}

		} else {

			// Shipping address is the same as billing
			if( empty( $post_data['card_address'] ) ) {
				edd_set_error( 'missing_address', __( 'Please enter a shipping address.', 'edd-flat-rate-shipping' ) );
			}

			if( empty( $post_data['card_city'] ) ) {
				edd_set_error( 'missing_city', __( 'Please enter a city for shipping.', 'edd-flat-rate-shipping' ) );
			}

			if( empty( $post_data['card_zip'] ) ) {
				edd_set_error( 'missing_zip', __( 'Please enter a zip/postal code for shipping.', 'edd-flat-rate-shipping' ) );
			}

			if( 'US' == $post_data['billing_country'] ) {

				if( empty( $post_data['card_state'] ) ) {
					edd_set_error( 'missing_state', __( 'Please select your state.', 'edd-flat-rate-shipping' ) );
				}

			} elseif( 'CA' == $post_data['billing_country'] ) {

				if( empty( $post_data['card_state'] ) ) {
					edd_set_error( 'missing_province', __( 'Please select your province.', 'edd-flat-rate-shipping' ) );
				}

			}

		}

	}


	/**
	 * Attach our shipping info to the payment gateway data
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return array
	 */
	public function set_shipping_info( $purchase_data, $valid_data ) {

		if( ! $this->cart_needs_shipping() ) {
			return $purchase_data;
		}

		$shipping_info = array();
		$customer      = EDD()->customers->get_customer_by( 'user_id', get_current_user_id() );

		// Check to see if shipping is different than billing
		if( isset( $_POST['edd_use_different_shipping'] ) || ! $this->has_billing_fields() ) {
			if ( ! isset( $_POST['existing_shipping_address'] ) || 'new' === $_POST['existing_shipping_address'] ) {
				$shipping_info['address']  = sanitize_text_field( $_POST['shipping_address'] );
				$shipping_info['address2'] = sanitize_text_field( $_POST['shipping_address_2'] );
				$shipping_info['city']     = sanitize_text_field( $_POST['shipping_city'] );
				$shipping_info['zip']      = sanitize_text_field( $_POST['shipping_zip'] );
				$shipping_info['country']  = sanitize_text_field( $_POST['shipping_country'] );

				// Shipping address is different
				switch ( $_POST['shipping_country'] ) :
					case 'US' :
						$shipping_info['state'] = isset( $_POST['shipping_state_us'] ) ? sanitize_text_field( $_POST['shipping_state_us'] ) : '';
						break;
					case 'CA' :
						$shipping_info['state'] = isset( $_POST['shipping_state_ca'] ) ? sanitize_text_field( $_POST['shipping_state_ca'] ) : '';
						break;
					default :
						$shipping_info['state'] = isset( $_POST['shipping_state_other'] ) ? sanitize_text_field( $_POST['shipping_state_other'] ) : '';
						break;
				endswitch;
			} else {
				if ( ! empty( $customer->id ) ) {
					$address_key   = absint( $_POST['existing_shipping_address'] );
					$shipping_info = $this->get_customer_shipping_address( $customer->id, $address_key );
				}
			}

		} else {

			$shipping_info['address']  = sanitize_text_field( $_POST['card_address'] );
			$shipping_info['address2'] = sanitize_text_field( $_POST['card_address_2'] );
			$shipping_info['city']     = sanitize_text_field( $_POST['card_city'] );
			$shipping_info['zip']      = sanitize_text_field( $_POST['card_zip'] );
			$shipping_info['state']    = sanitize_text_field( $_POST['card_state'] );
			$shipping_info['country']  = sanitize_text_field( $_POST['billing_country'] );

		}

		if ( ! empty( $customer->id ) ) {
			$this->add_customer_shipping_address( $customer->id, $shipping_info );
		}

		$purchase_data['user_info']['shipping_info'] = $shipping_info;

		return $purchase_data;

	}


	/**
	 * Sets up the shipping details for PayPal
	 *
	 * This makes it possible to use the Print Shipping Label feature in PayPal
	 *
	 * @since 1.1
	 *
	 * @access public
	 * @return array
	 */
	public function send_shipping_to_paypal( $paypal_args = array(), $purchase_data = array() ) {

		if( ! $this->cart_needs_shipping() ) {
			return $paypal_args;
		}

		$shipping_info = $purchase_data['user_info']['shipping_info'];

		$paypal_args['no_shipping'] = '0';
		$paypal_args['address1']    = $shipping_info['address'];
		$paypal_args['address2']    = $shipping_info['address2'];
		$paypal_args['city']        = $shipping_info['city'];
		$paypal_args['state']       = $shipping_info['country'] == 'US' ? $shipping_info['state'] : null;
		$paypal_args['country']     = $shipping_info['country'];
		$paypal_args['zip']         = $shipping_info['zip'];


		return $paypal_args;

	}

	/**
	 * Adds shipping address to PayPal Commerce API request.
	 * @link https://developer.paypal.com/docs/api/orders/v2/#definition-order_application_context
	 *
	 * @since 2.3.10
	 * @param array $order_data     The array of data sent to the PayPal API.
	 * @param array $purchase_data  The array of purchase data.
	 * @param int   $payment_id     The order/payment ID.
	 * @return array
	 */
	public function add_shipping_to_paypal_commerce( $order_data, $purchase_data, $payment_id ) {
		if ( ! $this->cart_needs_shipping() ) {
			return $order_data;
		}

		$address = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );
		if ( ! $address ) {
			$order_data['application_context']['shipping_preference'] = 'GET_FROM_FILE';

			return $order_data;
		}
		$order_data['application_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';
		$order_data['purchase_units'][0]['shipping']              = array(
			'name'    => array(
				'full_name' => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
			),
			'type'    => 'SHIPPING',
			'address' => array(
				'address_line_1' => $address['address'],
				'address_line_2' => $address['address2'],
				'admin_area_2'   => $address['city'],
				'admin_area_1'   => $address['state'],
				'postal_code'    => $address['zip'],
				'country_code'   => $address['country'],
			),
		);

		return $order_data;
	}

	/**
	 * Set a purchase as not shipped
	 *
	 * This is so that we can grab all purchases in need of being shipped
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function set_as_not_shipped( $payment_id = 0, $payment_data = array() ) {

		$shipping_info = ! empty( $payment_data['user_info']['shipping_info'] ) ? $payment_data['user_info']['shipping_info'] : false;

		if ( ! $shipping_info ) {
			return;
		}

		// Indicate that this purchase needs shipped
		edd_update_payment_meta( $payment_id, '_edd_payment_shipping_status', 1 );
	}

	/**
	 * Adds the shipping address to the order addresses table (EDD 3.0 only).
	 * In EDD 2.x, the shipping address is stored in the payment meta.
	 *
	 * @since 2.3.9
	 * @param int   $payment_id   The payment ID.
	 * @param array $payment_data The array of payment data.
	 * @return void
	 */
	public function add_shipping_address_to_order( $payment_id, $payment_data ) {
		if ( ! function_exists( 'edd_add_order_address' ) ) {
			return;
		}

		$shipping_info = empty( $payment_data['user_info']['shipping_info'] ) ? false : $payment_data['user_info']['shipping_info'];

		if ( ! $shipping_info ) {
			return;
		}

		unset( $shipping_info['id'] );
		$shipping_info['type']        = 'shipping';
		$shipping_info['order_id']    = $payment_id;
		$shipping_info['name']        = $payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'];
		$shipping_info['region']      = $shipping_info['state'];
		$shipping_info['postal_code'] = $shipping_info['zip'];
		$order_address                = edd_add_order_address( $shipping_info );
		$payment                      = new EDD_Payment( $payment_id );
		$this->add_customer_shipping_address(
			$payment->customer_id,
			$shipping_info
		);
	}

	/**
	 * Determines if a payment needs shipping.
	 * @param int $payment_id
	 *
	 * @since 2.2.3
	 * @return bool
	 */
	public function payment_needs_shipping( $payment_id = 0 ) {

		if ( empty( $payment_id ) ) {
			return false;
		}

		$needs_shipping = (bool) edd_flat_rate_shipping_get_order_shipping_address( $payment_id );

		return apply_filters( 'edd_payment_needs_shipping', $needs_shipping, $payment_id );
	}

	/**
	 * Adds the shipping details as an order section in EDD 3.0.
	 *
	 * @since 2.3.9
	 * @param array  $sections The array of order sections.
	 * @param object $order    The order object.
	 * @return array
	 */
	public function add_shipping_details_section( $sections, $order ) {

		$needs_shipping = $this->payment_needs_shipping( $order->id );
		if ( ! $needs_shipping ) {
			return $sections;
		}

		$sections[] = array(
			'id'       => 'shipping',
			'label'    => __( 'Shipping', 'easy-flat-rate-shipping' ),
			'icon'     => 'admin-multisite',
			'callback' => array( $this, 'show_shipping_order_section' ),
		);

		return $sections;
	}

	/**
	 * Shows the shipping details in EDD 3.0.
	 *
	 * @since 2.3.9
	 * @param object $order The order object.
	 * @return void
	 */
	public function show_shipping_order_section( $order ) {
		remove_action( 'edd_view_order_details_billing_after', array( $this, 'show_shipping_details' ), 10 );

		printf( '<h3 class="hndle">%s</h3>', esc_html__( 'Shipping Address', 'edd-flat-rate-shipping' ) );
		$this->do_shipping_address_order_details( $order->id );
	}

	/**
	 * Display shipping details in order details for EDD 2.x.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @return void
	 */
	public function show_shipping_details( $payment_id = 0 ) {

		if ( empty( $payment_id ) ) {
			$payment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		}

		$needs_shipping = $this->payment_needs_shipping( $payment_id );

		if ( ! $needs_shipping ) {
			return;
		}

		?>
		<div id="edd-shipping-details" class="postbox">
			<h3 class="hndle">
				<?php esc_html_e( 'Shipping Address', 'edd-flat-rate-shipping' ); ?>
			</h3>
			<div class="inside edd-clearfix">

				<?php $this->do_shipping_address_order_details( $payment_id ); ?>

				<?php do_action( 'edd_payment_shipping_details', $payment_id ); ?>

			</div><!-- /.inside -->
		</div><!-- /#edd-shipping-details -->
		<?php
	}

	/**
	 * Shows the shipping address form in the order details.
	 * Split into a separate function for EDD 3.0 to remove unneeded markup.
	 *
	 * @since 2.3.9
	 * @param int $payment_id The payment ID.
	 * @return void
	 */
	private function do_shipping_address_order_details( $payment_id ) {
		$address = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );
		$status  = edd_get_payment_meta( $payment_id, '_edd_payment_shipping_status', true );
		$shipped = $status == '2' ? true : false;
		?>
		<div id="edd-order-shipping-address">
			<div class="order-data-address">
				<div class="edd-form-group">
					<label for="edd-payment-shipping-address-0-address" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'Street Address Line 1:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<input id="edd-payment-shipping-address-0-address" type="text" name="edd-payment-shipping-address[0][address]" value="<?php echo esc_html( $address['address'] ); ?>" class="regular-text edd-form-group__input" />
					</div>
				</div>
				<div class="edd-form-group">
					<label for="edd-payment-shipping-address-0-address2" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'Street Address Line 2:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<input id="edd-payment-shipping-address-0-address2" type="text" name="edd-payment-shipping-address[0][address2]" value="<?php echo esc_html( $address['address2'] ); ?>" class="regular-text edd-form-group__input" />
					</div>
				</div>
				<div class="edd-form-group">
					<label for="edd-payment-shipping-address-0-city" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'Address City:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<input id="edd-payment-shipping-address-0-city" type="text" name="edd-payment-shipping-address[0][city]" value="<?php echo esc_html( $address['city'] ); ?>" class="regular-text edd-form-group__input" />
					</div>
				</div>
				<div class="edd-form-group">
					<label for="edd-payment-shipping-address-0-zip" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'ZIP/Postal Code:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<input id="edd-payment-shipping-address-0-zip" type="text" name="edd-payment-shipping-address[0][zip]" value="<?php echo esc_html( $address['zip'] ); ?>" class="regular-text edd-form-group__input" />
					</div>
				</div>
				<div class="edd-form-group">
					<label for="edd_payment_shipping_address_0_country" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'Country:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
					<?php
						echo EDD()->html->select(
							array(
								'options'          => edd_get_country_list(),
								'id'               => 'edd_payment_shipping_address_0_country',
								'name'             => 'edd-payment-shipping-address[0][country]',
								'selected'         => $address['country'],
								'show_option_all'  => false,
								'show_option_none' => false,
								'class'            => 'edd_countries_filter edd-form-group__input',
								'data'             => array(
									'nonce' => wp_create_nonce( 'edd-country-field-nonce' ),
								),
								'chosen'           => true,
							)
						);
					?>
					</div>
				</div>
				<div class="edd-form-group">
					<label for="edd_payment_shipping_address_0_state" class="order-data-address-line edd-form-group__label"><?php esc_html_e( 'State/Province:', 'edd-flat-rate-shipping' ); ?></label>
					<div class="edd-form-group__control">
						<?php
						$states = edd_get_shop_states( $address['country'] );
						if ( ! empty( $states ) ) {
							echo EDD()->html->select(
								array(
									'options'          => $states,
									'id'               => 'edd_payment_shipping_address_0_state',
									'name'             => 'edd-payment-shipping-address[0][state]',
									'selected'         => $address['state'],
									'show_option_all'  => false,
									'show_option_none' => false,
									'class'            => 'edd-form-group__input',
									'chosen'           => true,
								)
							);
						} else {
							?>
							<input id="edd-payment-shipping-address-0-state" type="text" name="edd-payment-shipping-address[0][state]" value="<?php echo esc_html( $address['state'] ); ?>" class="regular-text edd-form-group__input" />
							<?php
						}
						?>
					</div>
				</div>
				<div class="edd-form-group">
					<div class="edd-form-group__control">
						<input type="checkbox" id="edd-payment-shipped" name="edd-payment-shipped" value="1"<?php checked( $shipped, true ); ?>/>
						<label for="edd-payment-shipped">
							<?php esc_html_e( 'Check if this purchase has been shipped.', 'edd-flat-rate-shipping' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div><!-- /#edd-order-shipping-address -->
		<?php
	}

	/**
	 * Add the shipping info to the admin sales notice
	 *
	 * @access      public
	 * @since       1.1
	 * @return      string
	 */
	public function admin_sales_notice( $email = '', $payment_id = 0, $payment_data = array() ) {

		$payment = new EDD_Payment( $payment_id );
		$shipped = $payment->get_meta( '_edd_payment_shipping_status' );

		// Only modify the email if shipping info needs to be added
		if( '1' == $shipped ) {

			$shipping_info = edd_flat_rate_shipping_get_order_shipping_address( $payment_id );

			$country_name = edd_get_country_name( $shipping_info['country'] );
			$state_name   = edd_get_state_name( $shipping_info['country'], $shipping_info['state'] );

			$email .= "<p><strong>" . __( 'Shipping Details:', 'edd-flat-rate-shipping' ) . "</strong></p>";
			$email .= __( 'Address:', 'edd-flat-rate-shipping' ) . " " . $shipping_info['address'] . "<br/>";
			$email .= __( 'Address Line 2:', 'edd-flat-rate-shipping' ) . " " . $shipping_info['address2'] . "<br/>";
			$email .= __( 'City:', 'edd-flat-rate-shipping' ) . " " . $shipping_info['city'] . "<br/>";
			$email .= __( 'Zip/Postal Code:', 'edd-flat-rate-shipping' ) . " " . $shipping_info['zip'] . "<br/>";
			$email .= __( 'Country:', 'edd-flat-rate-shipping' ) . " " . $country_name . "<br/>";
			$email .= __( 'State:', 'edd-flat-rate-shipping' ) . " " . $state_name . "<br/>";

		}

		return $email;

	}

	/**
	 * Add the shipping address to the end of the payment receipt.
	 *
	 * @since 2.0
	 *
	 * @param object $payment
	 * @param array $edd_receipt_args
	 * @return void
	 */
	public function payment_receipt_after( $payment, $edd_receipt_args ) {

		$shipping_info = edd_flat_rate_shipping_get_order_shipping_address( $payment->ID );

		if ( ! $shipping_info ) {
			return;
		}

		$shipped = edd_get_payment_meta( $payment->ID, '_edd_payment_shipping_status', true );
		if ( '2' == $shipped ) {
			$new_status = '1';
		} else {
			$new_status = '2';
		}

		$toggle_url = esc_url( add_query_arg( array(
			'edd_action' => 'toggle_shipped_status',
			'order_id'   => $payment->ID,
			'new_status' => $new_status
		) ) );

		$toggle_text = $shipped == '2' ? __( 'Mark as not shipped', 'edd-flat-rate-shipping' ) : __( 'Mark as shipped', 'edd-flat-rate-shipping' );
		$user_info   = edd_get_payment_meta_user_info( $payment->ID );

		echo '<tr>';
		echo '<td><strong>' . __( 'Shipping Address', 'edd-flat-rate-shipping' ) . '</strong></td>';
		echo '<td>' . self::format_address( $user_info, $shipping_info ) . '<td>';
		echo '</tr>';

		if( current_user_can( 'edit_shop_payments' ) || ( function_exists( 'EDD_FES' ) && EDD_FES()->vendors->vendor_is_vendor() ) ) {

			echo '<tr>';
			echo '<td colspan="2">';
			echo '<a href="' . $toggle_url . '" class="edd-flat-rate-shipping-toggle-status">' . $toggle_text . '</a>';
			echo '</td>';
			echo '</tr>';

		}
	}

	/**
	 * Format an address based on name and address information.
	 *
	 * For translators, a sample default address:
	 *
	 * (1) First (2) Last
	 * (3) Street Address 1
	 * (4) Street Address 2
	 * (5) City, (6) State (7) ZIP
	 * (8) Country
	 *
	 * @since 2.0
	 *
	 * @param array $user_info
	 * @param array $address
	 * @return string $address
	 */
	public static function format_address( $user_info, $address ) {

		return apply_filters(
			'edd_shipping_address_format',
			sprintf(
				'<div><strong>%1$s %2$s</strong></div><div>%3$s</div><div>%4$s</div>%5$s, %6$s %7$s</div><div>%8$s</div>',
				$user_info['first_name'],
				$user_info['last_name'],
				$address['address'],
				$address['address2'],
				$address['city'],
				$address['state'],
				$address['zip'],
				$address['country']
			),
			$address,
			$user_info
		);
	}

	/**
	 * Mark a payment as shipped.
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function frontend_toggle_shipped_status() {

		$payment_id = absint( $_GET[ 'order_id' ] );
		$status     = ! empty( $_GET['new_status'] ) ? absint( $_GET['new_status'] ) : '1';
		$key        = edd_get_payment_key( $payment_id );

		if( function_exists( 'EDD_FES' ) ) {
			if ( ! EDD_FES()->vendors->vendor_can_view_receipt( false, $key ) ) {
				wp_safe_redirect( wp_get_referer() ); exit;
			}
		}

		edd_update_payment_meta( $payment_id, '_edd_payment_shipping_status', $status );

		wp_safe_redirect( wp_get_referer() );

		exit();
	}

	/**
	 * Add a shipping address to the customer meta
	 *
	 * @since 2.2.3
	 * @param int   $customer_id
	 * @param array $address
	 *
	 * @return bool
	 */
	public function add_customer_shipping_address( $customer_id = 0, $address = array() ) {
		if ( ! is_array( $address ) ) {
			return false;
		}

		$customer = new EDD_Customer( $customer_id );
		if ( empty( $customer->id ) ) {
			return false;
		}

		// In EDD 3.0, adds the address to the customer addresses table if it doesn't already exist.
		if ( function_exists( 'edd_maybe_add_customer_address' ) ) {
			$address['type']        = 'shipping';
			$address['name']        = $customer->name;
			$address['region']      = $address['state'];
			$address['postal_code'] = $address['zip'];

			return edd_maybe_add_customer_address(
				$customer->id,
				$address
			);
		}

		// Otherwise, handle the address for 2.x.
		global $wpdb;

		ksort( $address );

		// See if we have an existing address.
		$serialized_address = serialize( $address );
		$address_query      = $wpdb->prepare( "SELECT meta_id FROM $wpdb->customermeta WHERE customer_id = %d AND meta_key ='shipping_address' AND meta_value = %s LIMIT 1", $customer->id, $serialized_address );
		$address_exists     = $wpdb->get_var( $address_query );

		if ( ! empty( $address_exists ) ) {
			return false;
		}

		return $customer->add_meta( 'shipping_address', $address );
	}

	/**
	 * Remove a specific customer shipping address
	 *
	 * @since 2.2.3
	 * @param int  $customer_id
	 * @param bool $address_key
	 *
	 * @return bool
	 *
	 */
	public function remove_customer_shipping_address( $customer_id = 0, $address_key = false ) {
		if ( false === $address_key ) {
			return false;
		}

		$customer = new EDD_Customer( $customer_id );
		if ( empty( $customer->id ) ) {
			return false;
		}

		$address = $this->get_customer_shipping_address( $customer_id, $address_key );
		if ( empty( $address ) ) {
			return false;
		}

		// In EDD 3.0, delete the address from the customer addresses table.
		if ( function_exists( 'edd_delete_customer_address' ) ) {
			return edd_delete_customer_address( $address_key );
		}

		return $customer->delete_meta( 'shipping_address', $address );
	}

	/**
	 * Get a specific customer shipping address
	 *
	 * @since 2.2.3
	 * @param int $customer_id The customer ID.
	 * @param int $address_key In EDD 3.0, this is the customer address ID; in 2.x, it's an array key.
	 *
	 * @return array|boolean
	 */
	public function get_customer_shipping_address( $customer_id = 0, $address_key = 0 ) {
		$address = false;
		if ( function_exists( 'edd_get_customer_address_by' ) ) {
			$address = edd_get_customer_address_by( 'id', $address_key );
			if ( $address ) {
				return array(
					'id'       => $address->id,
					'name'     => $address->name,
					'address'  => $address->address,
					'address2' => $address->address2,
					'city'     => $address->city,
					'state'    => $address->region,
					'zip'      => $address->postal_code,
					'country'  => $address->country,
				);
			}
		}
		$addresses = $this->get_customer_shipping_addresses( $customer_id );
		if ( isset( $addresses[ $address_key ] ) ) {
			return $addresses[ $address_key ];
		}

		return $address;
	}

	/**
	 * Get all the customer shipping addresses
	 *
	 * @since 2.2.3
	 * @param int $customer_id
	 *
	 * @return array
	 */
	public function get_customer_shipping_addresses( $customer_id = 0 ) {
		$customer = new EDD_Customer( $customer_id );
		if ( empty( $customer->id ) ) {
			return array();
		}

		// In EDD 3.0, get the customer addresses from the database table.
		if ( function_exists( 'edd_get_customer_addresses' ) ) {
			$addresses = edd_get_customer_addresses(
				array(
					'customer_id' => $customer->id,
					'type'        => 'shipping',
				)
			);

			if ( $addresses ) {
				$addresses_to_return = array();
				foreach ( $addresses as $address ) {
					$addresses_to_return[] = array(
						'id'       => $address->id,
						'address'  => $address->address,
						'address2' => $address->address2,
						'city'     => $address->city,
						'state'    => $address->region,
						'zip'      => $address->postal_code,
						'country'  => $address->country,
					);
				}

				return $addresses_to_return;
			}
		}

		return $customer->get_meta( 'shipping_address', false );
	}

	/**
	 * Output the user shipping addresses on the profile editor
	 *
	 * @since 2.2.3
	 * @return void
	 */
	public function profile_editor_addresses() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$customer = EDD()->customers->get_customer_by( 'user_id', get_current_user_id() );
		if ( empty( $customer->id ) ) {
			return;
		}

		$addresses = $this->get_customer_shipping_addresses( $customer->id );
		if ( empty( $addresses ) ) {
			return;
		}
		?>

		<?php if ( ! empty( $addresses ) ) : ?>
			<legend for="edd_shipping_addresses"><?php _e( 'Shipping Addresses', 'edd-flat-rate-shipping' ); ?></legend>
			<ul class="edd-profile-shipping-addresses">
			<?php
			foreach ( $addresses as $key => $address ) :
				if ( ! empty( $address['id'] ) ) {
					$key = $address['id'];
				}
				?>
				<li class="edd-profile-shipping-address">
					<span class="edd-ss-address"><?php echo esc_html( $address['address'] ); ?></span>
					<span class="actions">
						&mdash;
						<?php
							$remove_url = wp_nonce_url(
								add_query_arg(
									array(
										'address_key' => urlencode( $key ),
										'edd_action'  => 'profile-remove-shipping-address',
										'redirect'    => esc_url( edd_get_current_page_url() ),
									)
								),
								'edd-remove-customer-shipping-address'
							);
						?>
						<a href="<?php echo esc_url( $remove_url ); ?>" class="delete"><?php esc_html_e( 'Remove', 'edd-flat-rate-shipping' ); ?></a>
					</span>
					<br />
					<?php if ( ! empty( $address['address2'] ) ) : ?>
						<span class="edd-ss-address2"><?php echo esc_html( $address['address2'] ); ?></span><br />
					<?php endif; ?>

					<?php if ( ! empty( $address['city'] ) ) : ?>
						<span class="edd-ss-city"><?php echo esc_html( $address['city'] ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $address['state'] ) ) : ?>
						<span class="edd-ss-state">
							<?php if ( ! empty( $address['city'] ) ) : ?>,&nbsp;<?php endif; ?>
							<?php echo esc_html( $address['state'] ); ?>
						</span>
						<br />
					<?php endif; ?>

					<?php if ( ! empty( $address['country'] ) ) : ?>
						<span class="edd-ss-country"><?php echo esc_html( $address['country'] ); ?></span><br />
					<?php endif; ?>

					<?php if ( ! empty( $address['zip'] ) ) : ?>
						<span class="edd-ss-zip"><?php echo esc_html( $address['zip'] ); ?></span><br />
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php
		endif;
	}

	/**
	 * Process the 'remove' URL on the profile editor when customers wish to remove a shipping address
	 *
	 * @since  2.2.3
	 * @return void
	 */
	function process_profile_editor_remove_address() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Pending users can't edit their profile
		if ( edd_user_pending_verification() ) {
			return false;
		}

		// Nonce security
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'edd-remove-customer-shipping-address' ) ) {
			return false;
		}

		if ( ! isset( $_GET['address_key'] ) || (int) $_GET['address_key'] !== absint( $_GET['address_key'] ) ) {
			return false;
		}

		$customer = new EDD_Customer( get_current_user_id(), true );

		if ( (int) $customer->user_id !== (int) get_current_user_id() ) {
			return;
		}

		if ( $this->remove_customer_shipping_address( $customer->id, absint( $_GET['address_key'] ) ) ) {

			$url = add_query_arg( 'updated', true, $_GET['redirect'] );

			$user          = wp_get_current_user();
			$user_login    = ! empty( $user->user_login ) ? $user->user_login : 'EDDBot';
			$customer_note = __( sprintf( 'Shipping address removed by %s', $user_login ), 'edd-flat-rate-shipping' );
			$customer->add_note( $customer_note );

		} else {
			edd_set_error( 'profile-remove-shipping_address-failure', __( 'Error removing shipping address from profile. Please try again later.', 'edd-flat-rate-shipping' ) );
			$url = $_GET['redirect'];
		}

		wp_safe_redirect( $url );
		exit;
	}

}

require_once dirname( __FILE__ ) . '/vendor/autoload.php';
\EDD\ExtensionUtils\v1\ExtensionLoader::loadOrQuit( __FILE__, 'edd_flat_rate_shipping', array(
	'php'                    => '5.3',
	'easy-digital-downloads' => '2.9',
) );

/**
 * Get everything running
 *
 * @since 1.0
 *
 * @access private
 * @return EDD_Flat_Rate_Shipping
 */
function edd_flat_rate_shipping_load() {
	return EDD_Flat_Rate_Shipping::get_instance();
}

/**
 * A nice function name to retrieve the instance that's created on plugins loaded
 *
 * @since 2.2.3
 * @return EDD_Flat_Rate_Shipping
 */
function edd_flat_rate_shipping() {
	return edd_flat_rate_shipping_load();
}

/**
 * Installs Flat Rate Shipping.
 */
function edd_flat_rate_shipping_install() {

	$current_version = get_option( 'edd_flat_rate_shipping_version' );

	if ( ! $current_version && function_exists( 'edd_set_upgrade_complete' ) ) {

		// When new upgrade routines are added, mark them as complete on fresh install
		$upgrade_routines = array(
			'ss_upgrade_customer_addresses',
		);

		foreach ( $upgrade_routines as $upgrade ) {
			edd_set_upgrade_complete( $upgrade );
		}

	}

	add_option( 'edd_flat_rate_shipping_version', EDD_FLAT_RATE_SHIPPING_VERSION, '', false );

}

/**
 * We don't want to run `edd_flat_rate_shipping_install()` directly in case the requirements
 * haven't been met (EDD not active, etc.). So we set a flag on installation, which we'll
 * check for later once the plugin is booted.
 *
 * @see EDD_Flat_Rate_Shipping::maybeRunInstall()
 */
register_activation_hook( __FILE__, function() {
	update_option( 'edd_flat_rate_shipping_run_install', time() );
} );
