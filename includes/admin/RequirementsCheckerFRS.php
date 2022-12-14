<?php
/**
 * Requirements Checker - Flat Rate Shipping
 *
 * Checks system requirements. As this class is used to check system requirements, we intentionally
 * use PHP that's compatible with lower versions.
 *
 * @package   edd-addon-tools
 * @copyright Copyright (c) 2021, Easy Digital Downloads
 * @license   GPL2+
 * @since     1.0
 */

class RequirementsCheckerFRS {

	/**
	 * @var array Requirements
	 */
	private $requirements = array();

	/**
	 * List of all dependencies (requirements) that we know about.
	 * The key is the ID that you would pass into `addRequirement()`
	 * and the value is the display name.
	 *
	 * @var string[]
	 */
	private $knownDependencies = array(
		'php'                    => 'PHP',
		'wp'                     => 'WordPress',
		'easy-digital-downloads' => 'Easy Digital Downloads',
	);

	/**
	 * Requirements constructor.
	 *
	 * @since 1.0
	 *
	 * @param array $requirements
	 */
	public function __construct( $requirements = array() ) {
		if ( ! empty( $requirements ) ) {
			foreach ( $requirements as $id => $requirement ) {
				$this->addRequirement( $id, $requirement );
			}
		}
	}

	/**
	 * Adds a new requirement.
	 *
	 * @since 1.0
	 *
	 * @param string       $id       Unique ID for the requirement.
	 * @param array|string $args     {
	 *                               Array of arguments.
	 *                               If this value is not an array, then this is assumed to be
	 *                               the minimum version required.
	 *
	 * @type string        $minimum  Minimum version required.
	 * @type string        $name     Display name for the requirement.
	 *                     }
	 *
	 * @return void
	 */
	public function addRequirement( $id, $args ) {
		if ( ! is_array( $args ) ) {
			$args = array( 'minimum' => $args );
		}

		$args = wp_parse_args( $args, array(
			'minimum' => '1',   // Minimum version number
			'name'    => isset( $this->knownDependencies[ $id ] ) ? $this->knownDependencies[ $id ] : '', // Display name
			'exists'  => false, // Whether or not this requirement exists.
			'current' => false, // The currently installed version number.
			'checked' => false, // Whether or not the requirement has been checked.
			'met'     => false, // Whether or not all requirements are met.
		) );

		$this->requirements[ sanitize_key( $id ) ] = $args;
	}

	/**
	 * Whether or not all requirements have been met.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function met() {
		$this->check();

		$requirements_met = true;

		// If any one requirement is not met, we return false.
		foreach ( $this->requirements as $requirement ) {
			if ( empty( $requirement['met'] ) ) {
				$requirements_met = false;
				break;
			}
		}

		return $requirements_met;
	}

	/**
	 * Checks the requirements.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private function check() {
		foreach ( $this->requirements as $requirement_id => $properties ) {
			switch ( $requirement_id ) {
				case 'php':
					$exists  = true;
					$version = phpversion();
					break;
				case 'wp':
					$exists  = true;
					$version = get_bloginfo( 'version' );
					break;
				case 'easy-digital-downloads':
					$version = defined( 'EDD_VERSION' ) ? EDD_VERSION : false;
					$exists  = defined( 'EDD_VERSION' );
					break;
				default:
					$version = false;
					break;
			}

			if ( ! empty( $version ) ) {
				$this->requirements[ $requirement_id ] = array_merge( $this->requirements[ $requirement_id ], array(
					'current' => $version,
					'checked' => true,
					'met'     => version_compare( $version, $properties['minimum'], '>=' ),
					'exists'  => isset( $exists ) ? $exists : $this->requirements[ $requirement_id ]['exists']
				) );
			}
		}
	}

	/**
	 * Returns requirements errors.
	 *
	 * @since 1.0
	 *
	 * @return WP_Error
	 */
	public function getErrors() {
		$error = new WP_Error();

		foreach ( $this->requirements as $requirement_id => $properties ) {
			if ( empty( $properties['met'] ) ) {
				$error->add( $requirement_id, $this->unmetRequirementDescription( $properties ) );
			}
		}

		return $error;
	}

	/**
	 * Generates an HTML error description.
	 *
	 * @since 1.0
	 *
	 * @param array $requirement
	 *
	 * @return string
	 */
	private function unmetRequirementDescription( $requirement ) {
		// Requirement exists, but is out of date.
		if ( ! empty( $requirement['exists'] ) ) {
			return sprintf(
				$this->unmetRequirementsDescriptionText(),
				'<strong>' . esc_html( $requirement['name'] ) . '</strong>',
				'<strong>' . esc_html( $requirement['minimum'] ) . '</strong>',
				'<strong>' . esc_html( $requirement['current'] ) . '</strong>'
			);
		}

		// Requirement could not be found.
		return sprintf(
			$this->unmetRequirementsMissingText(),
			esc_html( $requirement['name'] ),
			'<strong>' . esc_html( $requirement['minimum'] ) . '</strong>'
		);
	}

	/**
	 * Plugin specific text to describe a single unmet requirement.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	private function unmetRequirementsDescriptionText() {
		/* Translators: %1$s name of the requirement; %2$s required version; %3$s current version */
		return esc_html__( '%1$s: minimum required %2$s (you have %3$s)', 'easy-digital-downloads' );
	}

	/**
	 * Plugin specific text to describe a single missing requirement.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	private function unmetRequirementsMissingText() {
		/* Translators: %1$s name of the requirement; %2$s required version */
		return wp_kses( __( '<strong>Missing %1$s</strong>: minimum required %2$s', 'easy-digital-downloads' ), array( 'strong' => array() ) );
	}


}
