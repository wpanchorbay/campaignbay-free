<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Core;

/**
 * The file that defines the Campaign model class.
 *
 * A class definition that encapsulates all data and functionality for a single discount campaign.
 *
 * @link       https://wpanchorbay.com
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DateTime;
use DateTimeZone;
use Exception;
use WP_Error;
use WpabCampaignBay\Core\Validator;
use WpabCampaignBay\Helper\Filter;
use WpabCampaignBay\Helper\Logger;

/**
 * The Campaign model class.
 *
 * This class represents a single discount campaign and provides methods
 * to create, read, update, and delete campaign data using the custom campaigns table.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Campaign {


	/**
	 * The campaign ID.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var int
	 */
	public $id = 0;

	/**
	 * The campaign data from the database.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object|null
	 */
	private $data;

	/**
	 * A flat list of all product and variation IDs this campaign applies to.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var int[]
	 */
	private $applicable_product_ids = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param int|object $campaign The campaign ID or campaign data object.
	 */
	public function __construct( $campaign ) {
		if ( is_object( $campaign ) ) {
			$this->id   = $campaign->id;
			$this->data = $campaign;
		} elseif ( is_numeric( $campaign ) ) {
			$this->id = absint( $campaign );
			$this->load_data();
		}

		if ( ! $this->data ) {
			throw new Exception( 'Invalid campaign provided.' );
		}
	}






	/**
	 * Creates a new campaign.
	 *
	 * @since 1.0.0
	 * @param array $args The campaign arguments.
	 * @return Campaign The created campaign object.
	 * @throws Exception If validation fails.
	 */
	public static function create( $args ) {
		// validating main data
		$validator = new Validator( $args );
		$rules     = self::get_validation_rules( $args );
		// checking validation
		if ( ! $validator->validate( $rules ) ) {
			wpab_campaignbay_log( 'Validation failed for campaign: ' . $validator->get_first_error(), 'ERROR' );
			return new WP_Error(
				'rest_validation_error',
				$validator->get_first_error(),
				array(
					'status'  => 400,
					'details' => $validator->get_errors(),
					'data'    => $args,
				)
			);
		}
		// retriving validated data
		$data = $validator->get_validated_data();

		// validating tiers
		$tmp_tiers = array();
		if ( self::need_tier_validation( $data['type'] ) ) {
			foreach ( (array) $data['tiers'] as $tier ) {
				$tier_validator = new Validator( $tier );
				$tier_rules     = self::get_tiers_validation_rules( $data['type'] );
				if ( ! $tier_validator->validate( $tier_rules ) ) {
					wpab_campaignbay_log( 'Validation failed for tier: ' . $tier_validator->get_first_error(), 'WARNING' );
					return new WP_Error(
						'rest_validation_error',
						$tier_validator->get_first_error(),
						array(
							'status'  => 400,
							'details' => array( 'tiers' => array( $tier['id'] => $tier_validator->get_errors() ) ),
							'data'    => $tier,
						)
					);
				}

				$tmp_tiers[] = $tier_validator->get_validated_data();
			}
		}

		// validating settings
		$validated_settings = self::get_validated_settings( $data['settings'] ?? array(), $data['type'] );
		if ( is_wp_error( $validated_settings ) ) {
			return $validated_settings;
		}
		// validating conditions
		$validated_conditions = self::get_validated_conditions( $data['conditions'] ?? array(), $data['type'] );
		if ( is_wp_error( $validated_conditions ) ) {
			return $validated_conditions;
		}

		// json encoding json fields

		$data['tiers']      = wp_json_encode( $tmp_tiers ? $tmp_tiers : array() );
		$data['target_ids'] = wp_json_encode( isset( $data['target_ids'] ) ? $data['target_ids'] : array() );
		$data['settings']   = wp_json_encode( $validated_settings ? $validated_settings : array() );
		$data['conditions'] = wp_json_encode( $validated_conditions ? $validated_conditions : array() );

		// adding other default data
		$data['usage_count']   = 0;
		$data['date_created']  = current_time( 'mysql' );
		$data['date_modified'] = current_time( 'mysql' );
		$data['created_by']    = get_current_user_id();
		$data['updated_by']    = get_current_user_id();
		// is_exclude/schedule_enabled are NOT NULL columns; a null here (allowed by the nullable
		// validation rule) would make $wpdb->insert fail under strict SQL mode.
		$data['is_exclude']       = $data['is_exclude'] ?? false;
		$data['schedule_enabled'] = $data['schedule_enabled'] ?? false;
		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'campaignbay_campaigns';
			$formats    = array(
				'%s', // title
				'%s', // type
				'%s', // target_ids
				'%s', // conditions
				'%f', // discount
				'%s', // discount_type
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
			);

			//phpcs:ignore
			$result = $wpdb->insert($table_name, $data, $formats);

			if ( false === $result ) {
				throw new Exception( 'Failed to create campaign.' );
			}

			$campaign_id = $wpdb->insert_id;
			$campaign    = new self( $campaign_id );

			/**
			 * Fires after a new campaign is created and all its data is saved.
			 *
			 * @since 1.0.0
			 * @hook campaignbay_campaign_save
			 *
			 * @param int      $campaign_id The ID of the new campaign.
			 * @param Campaign $campaign    The campaign object.
			 */
			do_action( 'campaignbay_campaign_save', $campaign_id, $campaign );

			// Log the activity.
			Logger::get_instance()->log(
				'campaign_created',
				'created',
				array(
					'campaign_id' => $campaign->get_id(),
					'extra_data'  => array(
						'title' => $campaign->get_title(),
					),
				)
			);

			return $campaign;
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Error creating campaign: ' . $e->getMessage(), 'ERROR' );
			return new WP_Error(
				'rest_cannot_create',
				__( 'Cannot create campaign.', 'campaignbay' ),
				array(
					'status' => 500,
					'error'  => $e->getMessage(),
				)
			);
		}
	}



	/**
	 * Updates the campaign with new data.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, false on failure.
	 * @param mixed $partial Description.
	 * @param mixed $partial Description.
	 */
	public function update( $args, $partial = false ) {
		if ( $partial ) {
			$args = array_merge( (array) $this->data, $args );
		}
		$validator = new Validator( $args );
		$rules     = self::get_validation_rules( $args );

		if ( ! $validator->validate( $rules ) ) {
			wpab_campaignbay_log( 'Validation failed for campaign: ' . $validator->get_first_error(), 'WARNING' );
			//phpcs:ignore
			return new WP_Error('rest_validation_error', $validator->get_first_error(), array('status' => 400, 'details' => $validator->get_errors(), 'data' => $args));
		}
		$data = $validator->get_validated_data();
		// validating tiers
		$tmp_tiers = array();
		if ( self::need_tier_validation( $data['type'] ) ) {
			foreach ( (array) $data['tiers'] as $tier ) {
				$tier_validator = new Validator( $tier );
				$tier_rules     = self::get_tiers_validation_rules( $data['type'] );
				if ( ! $tier_validator->validate( $tier_rules ) ) {
					wpab_campaignbay_log( 'Validation failed for tier: ' . $tier_validator->get_first_error(), 'WARNING' );
					return new WP_Error(
						'rest_validation_error',
						$tier_validator->get_first_error(),
						array(
							'status'  => 400,
							'details' => array( 'tiers' => array( $tier['id'] => $tier_validator->get_errors() ) ),
							'data'    => $tier,
						)
					);
				}
				$tmp_tiers[] = $tier_validator->get_validated_data();
			}
		}
		// validating settings
		$validated_settings = $this->get_validated_settings( $data['settings'] ?? array(), $data['type'] );
		if ( is_wp_error( $validated_settings ) ) {
			return $validated_settings;
		}
		// validating conditions
		$validated_conditions = $this->get_validated_conditions( $data['conditions'] ?? array(), $data['type'] );
		if ( is_wp_error( $validated_conditions ) ) {
			return $validated_conditions;
		}
		// json encoding json fields. The fallback branches re-encode $this->data's existing
		// value, which is normally already-decoded (Campaign::load_data() decodes it) but isn't
		// guaranteed to be (some call sites construct Campaign from a raw, undecoded $wpdb row) --
		// self::encode_json_field() reuses an already-JSON string as-is instead of wp_json_encode()-ing
		// it a second time, to avoid double-encoding in that case.
		$data['target_ids'] = isset( $data['target_ids'] ) ? wp_json_encode( $data['target_ids'] ) : self::encode_json_field( $this->data->target_ids ?? array() );
		$data['settings']   = $validated_settings ? wp_json_encode( $validated_settings ) : self::encode_json_field( $this->data->settings ?? array() );
		$data['conditions'] = $validated_conditions ? wp_json_encode( $validated_conditions ) : self::encode_json_field( $this->data->conditions ?? array() );
		// array_key_exists on the raw $args (not isset($tmp_tiers), which is always true since
		// it's declared unconditionally above) so an update that doesn't send 'tiers' at all
		// preserves the existing tiers instead of silently wiping them to [].
		$data['tiers'] = array_key_exists( 'tiers', $args ) && $tmp_tiers ? wp_json_encode( $tmp_tiers ) : self::encode_json_field( $this->data->tiers ?? array() );
		// is_exclude/schedule_enabled are NOT NULL columns; a null here (allowed by the nullable
		// validation rule) would make $wpdb->update fail under strict SQL mode. Fall back to the
		// existing stored value (not false) when the request simply didn't send the field, so a
		// partial update (e.g. a status-only toggle) doesn't silently reset it.
		$data['is_exclude']       = array_key_exists( 'is_exclude', $args ) ? ( $data['is_exclude'] ?? false ) : ( $this->data->is_exclude ?? false );
		$data['schedule_enabled'] = array_key_exists( 'schedule_enabled', $args ) ? ( $data['schedule_enabled'] ?? false ) : ( $this->data->schedule_enabled ?? false );
		// adding other default data
		$data['date_modified'] = current_time( 'mysql' );
		$data['updated_by']    = get_current_user_id();

		try {

			global $wpdb;
			$table_name = $wpdb->prefix . 'campaignbay_campaigns';
			$formats    = array();
			$formats    = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			);
			if ( empty( $data ) ) {
				return true;
			}

			//phpcs:ignore
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $this->id ),
				$formats,
				array( '%d' )
			);
			if ( is_wp_error( $result ) || false === $result ) {
				return false;
			}

			// Reload data
			$this->load_data();

			/**
			 * Fires after a campaign is updated and all its data is saved.
			 *
			 * @since 1.0.0
			 * @hook campaignbay_campaign_save
			 *
			 * @param int      $campaign_id The ID of the updated campaign.
			 * @param Campaign $campaign    The campaign object.
			 */
			do_action( 'campaignbay_campaign_save', $this->id, $this );

			// Log the activity.
			Logger::get_instance()->log(
				'campaign_updated',
				'updated',
				array(
					'campaign_id' => $this->get_id(),
					'extra_data'  => array(
						'title' => $this->get_title(),
					),
				)
			);

			return true;
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Error updating campaign: ' . $e->getMessage(), 'ERROR' );
			return new WP_Error(
				'rest_cannot_update',
				__( 'Cannot update campaign.', 'campaignbay' ),
				array(
					'status' => 500,
					'error'  => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Encodes a JSON-column fallback value for storage, without double-encoding.
	 *
	 * $this->data's JSON columns (tiers, target_ids, settings, conditions) are normally
	 * already-decoded arrays (Campaign::load_data() decodes them on hydration), but a Campaign
	 * can also be constructed from a raw, undecoded $wpdb row elsewhere in the codebase -- in
	 * that case the value here would still be the raw JSON string, and wp_json_encode()-ing it
	 * again would double-encode it.
	 *
	 * @since 1.1.11
	 * @access private
	 * @param mixed $value The value to encode; either an already-decoded array or a raw JSON string.
	 * @return string The JSON-encoded value, safe to store.
	 */
	private static function encode_json_field( $value ) {
		return is_string( $value ) ? $value : wp_json_encode( $value );
	}

	/**
	 * Defines the primary validation rules for a campaign's data.
	 *
	 * This static method returns an associative array where each key represents a
	 * campaign property and the value is a string of validation rules. These rules
	 * are used to ensure data integrity before creating or updating a campaign.
	 * The rules are filterable to allow for extensibility.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array The array of validation rules for a campaign.
	 * @param mixed $args Description.
	 */
	private static function get_validation_rules( $args = null ) {
		$rules = array(
			'title'              => 'required|max:255',
			'type'               => 'required|in:earlybird,scheduled,quantity,bogo',
			'status'             => 'required|in:active,inactive,scheduled,expired',

			'discount_type'      => 'nullable|in:percentage,fixed',
			'discount_value'     => 'required_if:type,scheduled|numeric',
			'tiers'              => 'nullable|array',

			'target_type'        => 'nullable|in:entire_store,category,product,tag',
			'target_ids'         => 'required_if:target_type,category,product,tag|array_of_integers',
			'exclude_sale_items' => 'required|boolean',
			'is_exclude'         => 'nullable|boolean',

			'schedule_enabled'   => 'boolean|required_if:status,scheduled',
			'start_datetime'     => 'datetime|nullable',
			'end_datetime'       => 'datetime|nullable',

			'settings'           => 'nullable|array',
			'conditions'         => 'nullable|object',
			'usage_limit'        => 'nullable|integer',
		);
		if ( true === $args['schedule_enabled'] ) {
			if ( ( null === $args['start_datetime'] || '' === $args['start_datetime'] ) && ( null === $args['end_datetime'] || '' === $args['end_datetime'] ) ) {
				$rules['start_datetime'] = 'required|datetime';
			}
		}
		/**
		 * Filters the main validation rules for a campaign.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_get_campaign_validation_rules
		 *
		 * @param array $rules The array of validation rules.
		 */
		return apply_filters( 'campaignbay_get_campaign_validation_rules', $rules );
	}

	/**
	 * Defines the validation rules for the 'tiers' array, based on the campaign type.
	 *
	 * This static method returns a specific set of validation rules for the items
	 * within the 'tiers' array. The rules differ depending on whether the campaign
	 * is a 'quantity', 'earlybird', or 'bogo' type.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $type The type of the campaign (e.g., 'quantity', 'earlybird', 'bogo').
	 * @return array The array of validation rules for a single tier.
	 */
	private static function get_tiers_validation_rules( $type ) {
		$rules = array();
		if ( 'quantity' === $type ) {
			$rules = array(
				'id'    => 'nullable|integer',
				'min'   => 'required|integer|min:1|gte:previous_tier_max',
				'max'   => 'nullable|integer|min:1|gte:min',
				'value' => 'required|numeric|min:0|max_if:type,percentage,100',
				'type'  => 'required|in:percentage,currency',
			);
		} elseif ( 'earlybird' === $type ) {
			$rules = array(
				'id'       => 'nullable|integer',
				'quantity' => 'required|integer|min:1',
				'value'    => 'required|numeric|min:0|max_if:type,percentage,100',
				'type'     => 'required|in:percentage,currency',
				'total'    => 'required|integer|min:0',
			);
		} elseif ( 'bogo' === $type ) {
			$rules = array(
				'buy_quantity' => 'required|integer|min:1',
				'get_quantity' => 'required|integer|min:1',
			);
		}

		/**
		 * Filters the tier-specific validation rules for a campaign.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_get_tiers_validation_rules
		 *
		 * @param array  $rules The array of validation rules for a tier.
		 * @param string $type  The type of the campaign being validated.
		 */
		return apply_filters( 'campaignbay_get_tiers_validation_rules', $rules, $type );
	}

	/**
	 * Checking if campaign type need tiers validation
	 *
	 * @since 1.0.0
	 * @param string $type The type of the campaign.
	 * @return bool True if the campaign type requires tier validation, false otherwise.
	 */
	private static function need_tier_validation( $type ) {
		$result = false;
		if ( 'quantity' === $type || 'earlybird' === $type || 'bogo' === $type ) {
			$result = true;
		}
		/**
		 * Filters the need-tier-validation check for a campaign.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_need_tier_validation
		 *
		 * @param bool   $result The result of the need-tier-validation check.
		 * @param string  $type   The type of the campaign being validated.
		 */
		return apply_filters( 'campaignbay_need_tier_validation', $result, $type );
	}





	/**
	 * Validates the settings for a campaign.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $settings The settings to validate.
	 * @param string $type The type of the campaign being validated.
	 * @return array|null|WP_Error The validated settings, or null if validation fails.
	 */
	public static function get_validated_settings( $settings, $type ) {
		if ( null === $settings || ! is_array( $settings ) || empty( $settings ) ) {
			return null;
		}
		$rules   = array();
		$default = array();
		if ( 'scheduled' === $type || 'earlybird' === $type ) {
			$default = array(
				'display_as_regular_price' => false,
				'show_product_message'     => true,
				'message_format'           => '',
			);
			$rules   = array(
				'display_as_regular_price' => 'required|boolean',
				'show_product_message'     => 'required_if:display_as_regular_price,0|boolean',
				'message_format'           => 'nullable|string',
			);
		} elseif ( 'quantity' === $type ) {
			$default = array(
				'apply_as'                       => 'line_total',
				'cart_quantity_message_format'   => '',
				'cart_quantity_message_location' => 'line_item_name',
			);
			$rules   = array(
				'apply_as'                       => 'required|string|in:line_total,fee,coupon',
				'cart_quantity_message_format'   => 'nullable|string',
				'cart_quantity_message_location' => 'required|string|in:line_item_name,notice,dont_show',
			);
		} elseif ( 'bogo' === $type ) {
			$default = array(
				'show_bogo_message'          => true,
				'bogo_banner_message_format' => '',
				'cart_bogo_message_format'   => '',
				'bogo_cart_message_location' => 'line_item_name',
			);
			$rules   = array(
				'show_bogo_message'          => 'required|boolean',
				'bogo_banner_message_format' => 'nullable|string',
				'cart_bogo_message_format'   => 'nullable|string',
				'bogo_cart_message_location' => 'required|string|in:line_item_name,notice,dont_show',
			);
		}
		/**
		 * Filters the settings validation rules for a campaign.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_get_settings_validation_rules
		 *
		 * @param array $rules The array of validation rules.
		 * @param string $type The type of the campaign being validated.
		 */
		$rules = apply_filters( 'campaignbay_get_settings_validation_rules', $rules, $type );

		$validator = new Validator( array_merge( $default, $settings ) );

		if ( ! $validator->validate( $rules ) ) {
			wpab_campaignbay_log( 'Validation failed for settings: ' . $validator->get_first_error(), 'WARNING' );
			return new WP_Error(
				'rest_validation_error',
				$validator->get_first_error(),
				array(
					'status'  => 400,
					'details' => array( 'settings' => $validator->get_errors() ),
				)
			);
		}

		return $validator->get_validated_data();
	}


	/**
	 * Function.
	 */
	public static function get_validated_conditions( $conditions ) {
		// old campaigns dont have match_type default , raising error on import
		$conditions['match_type'] = $conditions['match_type'] ?? 'all';
		$validator                = new Validator( $conditions );
		$rules                    = array(
			'match_type' => 'required|string|in:all,any',
			'rules'      => 'nullable|array',
		);
		if ( ! $validator->validate( $rules ) ) {
			wpab_campaignbay_log( 'Validation failed for conditions: ' . $validator->get_first_error(), 'WARNING' );
			return new WP_Error(
				'rest_validation_error',
				$validator->get_first_error(),
				array(
					'status'  => 400,
					'details' => array( 'conditions' => $validator->get_errors() ),
				)
			);
		}
		$validated_data       = $validator->get_validated_data();
		$validated_rules_data = array();
		$default_rules        = array(
			'user_role'  => array(
				'option'      => 'required|string',
				'is_included' => 'required|boolean',
			),
			'user_roles' => array(
				'options'     => 'required|array',
				'is_included' => 'required|boolean',
			),
			'users'      => array(
				'options'     => 'required|array',
				'is_included' => 'required|boolean',
			),
			'cart_total' => array(
				'value'    => 'required|numeric|min:0',
				'operator' => 'required|string|in:>=,<=,==,>,<',
			),
		);
		/**
		 * Filters the default conditions validation rules.
		 *
		 * This filter allows adding or modifying validation rules for different condition types.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_get_conditions_validation_rules
		 *
		 * @param array $default_rules The array of default validation rules.
		 * @return array The array of default validation rules.
		 */
		$default_rules = apply_filters( 'campaignbay_get_conditions_validation_rules', $default_rules );
		// A campaign with no conditions configured has no 'rules' key at all (not an empty
		// array), which would otherwise trigger a foreach-on-null warning on every such save.
		foreach ( $conditions['rules'] ?? array() as $index => $rule ) {
			$condition_validator = new Validator( $rule['condition'] );
			$validation_rules    = $default_rules[ $rule['type'] ];
			if ( ! $condition_validator->validate( $validation_rules ) ) {
				wpab_campaignbay_log( 'Condition validation failed: ' . $condition_validator->get_first_error(), 'ERROR' );
				wpab_campaignbay_log( $condition_validator->get_errors(), 'WARNING' );
				wpab_campaignbay_log( $rule['condition'], 'WARNING' );
				return new WP_Error(
					'rest_validation_error',
					$condition_validator->get_first_error(),
					array(
						'status'  => 400,
						'details' => array( 'rules' => array( $index => $condition_validator->get_errors() ) ),
					)
				);
			}
			$validated_rules_data[] = array(
				'type'      => $rule['type'],
				'condition' => $condition_validator->get_validated_data(),
			);
		}
		$validated_data['rules'] = $validated_rules_data;
		return $validated_data;
	}


	/**
	 * Deletes a campaign.
	 *
	 * @since 1.0.0
	 * @param int  $campaign_id The campaign ID to delete.
	 * @param bool $force_delete Whether to force delete (unused for compatibility).
	 * @return bool True on success, false on failure.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public static function delete( $campaign_id, $force_delete = true ) {
		$campaign = new self( $campaign_id );
		$title    = $campaign->get_title();

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';
		/**
		 * Fires before a campaign is deleted.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_before_campaign_delete
		 *
		 * @param int $campaign_id The ID of the deleted campaign.
		 */
		do_action( 'campaignbay_before_campaign_delete', $campaign_id );

		//phpcs:ignore
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $campaign_id ),
			array( '%d' )
		);

		/**
		 * Fires after a campaign is deleted.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_campaign_delete
		 *
		 * @param int $campaign_id The ID of the deleted campaign.
		 */
		do_action( 'campaignbay_campaign_delete', $campaign_id );

		// Log the activity.
		Logger::get_instance()->log(
			'campaign_deleted',
			'deleted',
			array(
				'campaign_id' => $campaign_id,
				'extra_data'  => array(
					'title' => $title,
				),
			)
		);

		return false !== $result;
	}


	/**
	 * Gets the raw campaign data object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return object|null The campaign data object.
	 */
	public function get_data() {
		return $this->data;
	}
	/**
	 * Gets the campaign ID.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return int The campaign ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Gets the campaign title.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return int The campaign title.
	 */
	public function get_title() {
		return $this->data->title;
	}

	/**
	 * Gets the campaign status.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return int The campaign status.
	 */
	public function get_status() {
		return $this->data->status;
	}

	/**
	 * Sets the campaign status.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $status The campaign status.
	 * @return bool True if the status was updated successfully, false otherwise.
	 */
	public function set_status( $status ) {
		if ( empty( $status ) ) {
			return false;
		}
		$allowed_statuses = array( 'active', 'inactive', 'scheduled', 'expired' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			wpab_campaignbay_log(
				// Translators: %s is the invalid status provided.
				sprintf( __( 'Invalid status "%s" provided. Status must be one of: active, inactive, scheduled, expired.', 'campaignbay' ), $status ),
				'ERROR'
			);
			return false;
		}

		try {

			global $wpdb;
			$table_name = $wpdb->prefix . 'campaignbay_campaigns';

			$new_schedule_enabled = 'expired' === $this->data->status ? 0 : $this->data->schedule_enabled;
			wpab_campaignbay_log( '________ name: ' . $this->data->name, 'INFO' );
			wpab_campaignbay_log( '________ status: ' . $this->data->status, 'INFO' );
			wpab_campaignbay_log( 'New schedule enabled: ' . $new_schedule_enabled, 'INFO' );

			//phpcs:ignore
			$result = $wpdb->update(
				$table_name,
				array(
					'status'           => $status,
					'schedule_enabled' => $new_schedule_enabled,
				),
				array(
					'id' => $this->id,
				),
				array(
					'%s',
					'%d',
				),
				array(
					'%d',
				)
			);
			if ( is_wp_error( $result ) || false === $result ) {
				return false;
			}

			// Reload data
			$this->load_data();

			/**
			 * Fires after a campaign is updated and all its data is saved.
			 *
			 * @since 1.0.0
			 * @hook campaignbay_campaign_save
			 *
			 * @param int      $campaign_id The ID of the updated campaign.
			 * @param Campaign $campaign    The campaign object.
			 */
			do_action( 'campaignbay_campaign_save', $this->id, $this );

			// Log the activity.
			Logger::get_instance()->log(
				'campaign_updated',
				'updated',
				array(
					'campaign_id' => $this->get_id(),
					'extra_data'  => array(
						'title' => $this->get_title(),
					),
				)
			);

			return true;
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Error updating campaign: ' . $e->getMessage(), 'ERROR' );
			return new WP_Error(
				'rest_cannot_update',
				__( 'Cannot update campaign.', 'campaignbay' ),
				array(
					'status' => 500,
					'error'  => $e->getMessage(),
				)
			);
		}
	}


	/**
	 * Gets the campaign type.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return int The campaign type.
	 */
	public function get_type() {
		return $this->data->type;
	}

	/**
	 * Gets the discount type.
	 *
	 * @since 1.0.0
	 * @return string|null The discount type.
	 */
	public function get_discount_type() {
		return $this->data->discount_type ?? null;
	}

	/**
	 * Gets the discount value.
	 *
	 * @since 1.0.0
	 * @return float|null The discount value.
	 */
	public function get_discount_value() {
		return isset( $this->data->discount_value ) ? floatval( $this->data->discount_value ) : null;
	}

	/**
	 * Gets the campaign tiers.
	 *
	 * @since 1.0.0
	 * @return array The campaign tiers.
	 */
	public function get_tiers() {
		if ( is_string( $this->data->tiers ) ) {
			$tiers = json_decode( $this->data->tiers, true ) ?? array();
		} else {
			$tiers = $this->data->tiers ?? array();
		}

		// An open-ended final tier is stored with an empty string max; normalize it to null
		// so numeric comparisons against it (e.g. `null === $tier['max']`) work as intended.
		foreach ( $tiers as &$tier ) {
			if ( isset( $tier['max'] ) && '' === $tier['max'] ) {
				$tier['max'] = null;
			}
		}
		unset( $tier );

		return $tiers;
	}

	/**
	 * Gets the campaign conditions.
	 *
	 * @since 1.0.0
	 * @return array The campaign conditions.
	 */
	public function get_conditions() {
		if ( is_string( $this->data->conditions ) ) {
			return json_decode( $this->data->conditions, true ) ?? array();
		}
		return $this->data->conditions ?? array();
	}

	/**
	 * Gets the campaign settings.
	 *
	 * @since 1.0.0
	 * @return array The campaign settings.
	 */
	public function get_settings() {
		if ( is_string( $this->data->settings ) ) {
			return json_decode( $this->data->settings, true ) ?? array();
		}
		return $this->data->settings ?? array();
	}

	/**
	 * Gets the target type.
	 *
	 * @since 1.0.0
	 * @return string|null The target type.
	 */
	public function get_target_type() {
		return $this->data->target_type ?? null;
	}

	/**
	 * Gets the target IDs.
	 *
	 * @since 1.0.0
	 * @return array The target IDs.
	 */
	public function get_target_ids() {
		if ( is_string( $this->data->target_ids ) ) {
			return json_decode( $this->data->target_ids, true ) ?? array();
		}
		return $this->data->target_ids ?? array();
	}

	/**
	 * Gets whether sale items are excluded.
	 *
	 * @since 1.0.0
	 * @return bool True if sale items are excluded, false otherwise.
	 */
	public function get_exclude_sale_items() {
		return ! empty( $this->data->exclude_sale_items );
	}

	/**
	 * Gets whether products are excluded (is_exclude).
	 *
	 * @since 1.0.0
	 * @return bool True if products are excluded, false otherwise.
	 */
	public function get_is_exclude() {
		return ! empty( $this->data->is_exclude );
	}

	/**
	 * Gets the usage limit.
	 *
	 * @since 1.0.0
	 * @return int|null The usage limit, or null if unlimited.
	 */
	public function get_usage_limit() {
		return isset( $this->data->usage_limit ) ? intval( $this->data->usage_limit ) : null;
	}

	/**
	 * Gets whether scheduling is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if scheduling is enabled, false otherwise.
	 */
	public function get_schedule_enabled() {
		return ! empty( $this->data->schedule_enabled );
	}



	/**
	 * Gets the timestamp used by the "most recently started wins" priority method.
	 *
	 * Uses the campaign's start date, falling back to its creation date when no start
	 * date is set. Both values are stored in site-local time, so they are comparable.
	 *
	 * @since 1.1.18
	 * @return int Unix-like timestamp (0 when no usable date exists).
	 */
	public function get_priority_timestamp() {
		$date = $this->get_start_datetime();
		if ( empty( $date ) ) {
			$date = $this->data->date_created ?? '';
		}
		$timestamp = empty( $date ) ? false : strtotime( $date );
		return false === $timestamp ? 0 : (int) $timestamp;
	}

	/**
	 * Gets the start datetime string.
	 *
	 * @since 1.0.0
	 * @return string|null The start datetime in 'Y-m-d H:i:s' format, or null if not set.
	 */
	public function get_start_datetime() {
		if ( empty( $this->data->start_datetime ) ) {
			return null;
		}
		return $this->data->start_datetime;
	}

	/**
	 * Gets the end datetime string.
	 *
	 * @since 1.0.0
	 * @return string|null The end datetime in 'Y-m-d H:i:s' format, or null if not set.
	 */
	public function get_end_datetime() {
		if ( empty( $this->data->end_datetime ) ) {
			return null;
		}
		return $this->data->end_datetime;
	}

	/**
	 * Function.
	 */
	public function get_utc_time( $date_string ) {

		if ( empty( $date_string ) ) {
			return null;
		}

		try {
			$date = new DateTime( $date_string, new DateTimeZone( wp_timezone_string() ) );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Function.
	 */
	public function get_time_stamp( $date_string ) {
		$utc_datetime = $this->get_utc_time( $date_string );
		return $utc_datetime ? strtotime( $utc_datetime ) : null;
	}

	/**
	 * Gets the start datetime string and converts it to the UTC timezone.
	 *
	 * @since 1.0.0
	 * @return string|null The start datetime in 'Y-m-d H:i:s' format (UTC), or null if not set.
	 */
	public function get_start_datetime_utc() {
		$start_datetime_site = $this->data->start_datetime;

		if ( empty( $start_datetime_site ) ) {
			return null;
		}

		try {
			$date = new DateTime( $start_datetime_site, new DateTimeZone( wp_timezone_string() ) );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Invalid start_datetime format for campaign #' . $this->id, 'ERROR' );
			return null;
		}
	}

	/**
	 * Gets the end datetime string and converts it to the UTC timezone.
	 *
	 * @since 1.0.0
	 * @return string|null The end datetime in 'Y-m-d H:i:s' format (UTC), or null if not set.
	 */
	public function get_end_datetime_utc() {
		$end_datetime_site = $this->data->end_datetime;

		if ( empty( $end_datetime_site ) ) {
			return null;
		}

		try {
			$date = new DateTime( $end_datetime_site, new DateTimeZone( wp_timezone_string() ) );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Invalid end_datetime format for campaign #' . $this->id, 'ERROR' );
			return null;
		}
	}

	/**
	 * Gets the start datetime as a UTC Unix timestamp.
	 *
	 * @since 1.0.0
	 * @return int|null The Unix timestamp, or null if no start date is set.
	 */
	public function get_start_timestamp() {
		$utc_datetime = $this->get_start_datetime_utc();
		return $utc_datetime ? strtotime( $utc_datetime ) : null;
	}

	/**
	 * Gets the end datetime as a UTC Unix timestamp.
	 *
	 * @since 1.0.0
	 * @return int|null The Unix timestamp, or null if no end date is set.
	 */
	public function get_end_timestamp() {
		$utc_datetime = $this->get_end_datetime_utc();
		return $utc_datetime ? strtotime( $utc_datetime ) : null;
	}

	/**
	 * Gets the last modified date of the campaign.
	 *
	 * @since 1.0.0
	 * @return string|null The last modified date.
	 */
	public function get_date_modified() {
		return $this->data->date_modified ?: null;
	}


	/**
	 * Gets the current usage count for the campaign.
	 *
	 * @since 1.0.0
	 * @return int The number of times the campaign has been used on successful orders.
	 */
	public function get_usage_count() {
		return (int) $this->data->usage_count;
	}


	/**
	 * Load campaign data from the database.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function load_data() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		//phpcs:ignore
		$this->data = $wpdb->get_row(
			$wpdb->prepare(
				//phpcs:ignore
				"SELECT * FROM {$table_name} WHERE id = %d",
				$this->id
			)
		);

		// Decode JSON fields
		if ( $this->data ) {
			$this->data->target_ids = ! empty( $this->data->target_ids ) ? json_decode( $this->data->target_ids, true ) : array();
			$this->data->tiers      = ! empty( $this->data->tiers ) ? json_decode( $this->data->tiers, true ) : array();
			$this->data->conditions = ! empty( $this->data->conditions ) ? json_decode( $this->data->conditions, true ) : array();
			$this->data->settings   = ! empty( $this->data->settings ) ? json_decode( $this->data->settings, true ) : array();
		}
	}





	/**
	 * Increments the usage count for the campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return bool True on success, false on failure.
	 */
	public function increment_usage_count() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';

		//phpcs:ignore 
		$result = $wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore
				"UPDATE {$campaigns_table}
             SET 
                usage_count = usage_count + 1,
                status = CASE 
                            WHEN usage_limit IS NOT NULL AND usage_limit > 0 AND (usage_count + 1) >= usage_limit 
                            THEN 'expired' 
                            ELSE status 
                         END
             WHERE id = %d",
				$this->id
			)
		);

		if ( is_wp_error( $result ) || false === $result ) {
			wpab_campaignbay_log( 'Failed to increment usage count for campaign: #' . $this->get_id(), 'ERROR' );
			return false;
		}

		$this->data->usage_count = (int) $this->data->usage_count + 1;

		if ( null !== $this->data->usage_limit && $this->data->usage_limit > 0 && $this->data->usage_count >= $this->data->usage_limit ) {
			$this->data->status = 'expired';
		}

		wpab_campaignbay_log( 'Usage count incremented for campaign: #' . $this->get_id() . ' ' . $this->get_title() . ' - New count: ' . $this->data->usage_count, 'INFO' );

		/**
		 * Fires after a campaign's usage count is updated.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_campaign_usage_incremented
		 *
		 * @param int      $campaign_id The ID of the updated campaign.
		 * @param Campaign $campaign    The campaign object with the new usage count.
		 */
		do_action( 'campaignbay_campaign_usage_incremented', $this->id, $this );

		return true;
	}

	/**
	 * Decrements the usage count for the campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return bool True on success, false on failure.
	 */
	public function decrement_usage_count() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';

		//phpcs:ignore 
		$result = $wpdb->query(
			$wpdb->prepare(
				//phpcs:ignore
				"UPDATE {$campaigns_table}
             SET 
                usage_count = CASE 
                                WHEN usage_count > 0 
                                THEN usage_count - 1 
                                ELSE 0 
                             END,
                status = CASE 
                            WHEN usage_limit IS NOT NULL AND usage_limit > 0 AND (usage_count - 1) >= usage_limit 
                            THEN 'expired' 
                            ELSE status 
                         END
             WHERE id = %d",
				$this->id
			)
		);

		if ( is_wp_error( $result ) || false === $result ) {
			wpab_campaignbay_log( 'Failed to decrement usage count for campaign: #' . $this->get_id(), 'ERROR' );
			return false;
		}

		// UPDATING LOCAL DATA
		$this->data->usage_count = max( 0, (int) $this->data->usage_count - 1 );

		if ( null !== $this->data->usage_limit && $this->data->usage_limit > 0 && $this->data->usage_count >= $this->data->usage_limit ) {
			$this->data->status = 'expired';
		}

		wpab_campaignbay_log( 'Usage count decremented for campaign: #' . $this->get_id() . ' ' . $this->get_title() . ' - New count: ' . $this->data->usage_count, 'INFO' );

		/**
		 * Fires after a campaign's usage count is updated.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_campaign_usage_decremented
		 *
		 * @param int      $campaign_id The ID of the updated campaign.
		 * @param Campaign $campaign    The campaign object with the new usage count.
		 */
		do_action( 'campaignbay_campaign_usage_decremented', $this->id, $this );

		return true;
	}


	/**
	 * Checks if this campaign applies to a specific product.
	 *
	 * @since 1.0.0
	 * @param int|WC_Product $product The product ID or WC_Product object.
	 * @return bool True if the campaign applies to the product, false otherwise.
	 */
	public function is_applicable_to_product( $product ) {

		$result = Filter::get_instance()->match( $product, $this );
		return $result;
	}
}
