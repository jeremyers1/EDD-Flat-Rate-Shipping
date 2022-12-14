<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EDD_Flat_Rate_Shipping_Settings {

	public function __construct() {
		add_filter( 'edd_settings_sections_extensions',    array( $this, 'settings_section' ) );
		add_filter( 'edd_settings_sections_emails',        array( $this, 'emails_section' ) );
		add_filter( 'edd_settings_extensions',             array( $this, 'settings' ), 1 );
		add_filter( 'edd_settings_emails',                 array( $this, 'emails' ) );
	}

	/**
	 * Add Flat Rate Shipping settings section
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return array
	 */
	public function settings_section( $sections ) {
		$sections['edd-flat-rate-shipping-settings'] = __( 'Flat Rate Shipping', 'edd-flat-rate-shipping' );
		return $sections;
	}

	/**
	 * Add Flat Rate Shipping emails section
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return array
	 */
	public function emails_section( $sections ) {
		$sections['edd-flat-rate-shipping-emails'] = __( 'Flat Rate Shipping', 'edd-flat-rate-shipping' );
		return $sections;
	}

	/**
	 * Add Flat Rate Shipping settings
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return array
	 */
	public function settings( $settings ) {
		$flat_rate_shipping_settings = array(
			array(
				'id' => 'edd_flat_rate_shipping_license_header',
				'name' => '<strong>' . __( 'Flat Rate Shipping', 'edd-flat-rate-shipping' ) . '</strong>',
				'desc' => '',
				'type' => 'header',
				'size' => 'regular'
			),
			array(
				'id' => 'edd_flat_rate_shipping_base_country',
				'name' => __( 'Base Region', 'edd-flat-rate-shipping'),
				'desc' => __( 'Choose the country your store is based in', 'edd-flat-rate-shipping'),
				'type'  => 'select',
				'options' => edd_get_country_list()
			),
			array(
				'id' => 'edd_flat_rate_shipping_domestic_rate',
				'name' => __( 'Domestic Rate', 'edd-flat-rate-shipping'),
				'desc' => __( 'Enter amount for flat rate domestic shipping', 'edd-flat-rate-shipping'),
				'type' => 'number',
				'class' => 'edd-flat-rate-settings-country',
				'step' => '0.01',
			),
			array(
				'id' => 'edd_flat_rate_shipping_international_rate',
				'name' => __( 'International Rate', 'edd-flat-rate-shipping'),
				'desc' => __( 'Enter amount for flat rate international shipping', 'edd-flat-rate-shipping'),
				'type' => 'number',
				'class' => 'edd-flat-rate-settings-country',
				'step' => '0.01',
			),
			array(
				'id'   => 'flat_rate_shipping_disable_tax_on_shipping',
				'name' => __( 'Disable tax on shipping fees', 'edd-flat-rate-shipping' ),
				'desc' => __( 'By default, Flat Rate Shipping charges tax on shipping costs. Check this box to avoid charging tax on shipping costs.', 'edd-flat-rate-shipping' ),
				'type' => 'checkbox',
			)
		);

		if ( version_compare( EDD_VERSION, 3.0, '>=' ) ) {
			$flat_rate_shipping_settings = array( 'edd-flat-rate-shipping-settings' => $flat_rate_shipping_settings );
		}

		return array_merge( $settings, $flat_rate_shipping_settings );
	}

	/**
	 * Display the email settings for Flat Rate Shipping
	 *
	 * @since 1.0.0
	 * @param $settings
	 *
	 * @return array
	 */
	public function emails( $settings ) {
		$flat_rate_shipping_settings = array(
			array(
				'id'   => 'edd_flat_rate_shipping_emails_header',
				'name' => '<strong>' . __( 'Flat Rate Shipping Emails', 'edd-flat-rate-shipping' ) . '</strong>',
				'desc' => '',
				'type' => 'header',
				'size' => 'regular',
			),
			array(
				'id'          => 'tracking_ids_subject',
				'name'        => __( 'Tracking ID Email Subject Line', 'edd-flat-rate-shipping' ),
				'desc'        => __( 'The subject line used when sending shipment tracking information to customers.','edd-flat-rate-shipping' ),
				'type'        => 'text',
				'allow_blank' => false,
				'std'         => __( 'Your order has shipped!', 'edd-flat-rate-shipping' ),
			),
			array(
				'id'          => 'tracking_ids_heading',
				'name'        => __( 'Tracking ID Email Heading', 'edd-flat-rate-shipping' ),
				'desc'        => __( 'The heading used in the email body content when sending shipment tracking information to customers.','edd-flat-rate-shipping' ),
				'type'        => 'text',
				'allow_blank' => false,
				'std'         => __( 'Your order has shipped!', 'edd-flat-rate-shipping' ),
			),
			array(
				'id'          => 'tracking_ids_email',
				'name'        => __( 'Tracking ID Email', 'edd-flat-rate-shipping' ),
				'desc'        => __( 'Enter the text that is used when sending shipment tracking information to customers. HTML is accepted. Available template tags:','edd-flat-rate-shipping' ) . '<br/>' . edd_get_emails_tags_list(),
				'type'        => 'rich_editor',
				'allow_blank' => false,
				'std'         => edd_flat_rate_shipping()->tracking->get_default_tracking_email_message(),
			),
		);

		if ( version_compare( EDD_VERSION, 3.0, '>=' ) ) {
			$flat_rate_shipping_settings = array( 'edd-flat-rate-shipping-emails' => $flat_rate_shipping_settings );
		}

		return array_merge( $settings, $flat_rate_shipping_settings );
	}
}