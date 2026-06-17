<?php
/**
 * Plugin Name: Aspen Team Seats Expansion
 * Description: Adds shortcodes for displaying and restricting content based on the current user's WooCommerce Memberships for Teams status.
 * Version: 0.1.0
 * Author: Aspen
 * Text Domain: aspen-team-seats-expansion
 * Requires Plugins: woocommerce-memberships, woocommerce-memberships-for-teams
 *
 * @package AspenTeamSeatsExpansion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Aspen_Team_Seats_Expansion' ) ) {
	/**
	 * Registers Team Seats Expansion shortcodes.
	 */
	final class Aspen_Team_Seats_Expansion {
		/**
		 * Singleton instance.
		 *
		 * @var Aspen_Team_Seats_Expansion|null
		 */
		private static $instance = null;

		/**
		 * Return the singleton instance.
		 *
		 * @return Aspen_Team_Seats_Expansion
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Wire WordPress hooks.
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_shortcodes' ) );
		}

		/**
		 * Register public shortcodes.
		 *
		 * @return void
		 */
		public function register_shortcodes() {
			add_shortcode( 'teamx_name', array( $this, 'team_name_shortcode' ) );
			add_shortcode( 'teamx_restrict', array( $this, 'restrict_shortcode' ) );
		}

		/**
		 * Display the current user's team name.
		 *
		 * @return string
		 */
		public function team_name_shortcode() {
			$team = $this->get_current_user_team();

			if ( ! $team ) {
				return '';
			}

			return esc_html( $this->get_team_name( $team ) );
		}

		/**
		 * Conditionally display enclosed content based on team plan membership.
		 *
		 * @param array<string,mixed> $atts Shortcode attributes.
		 * @param string|null         $content Enclosed shortcode content.
		 * @return string
		 */
		public function restrict_shortcode( $atts, $content = null ) {
			$atts = shortcode_atts(
				array(
					'plan' => '',
					'mode' => 'show',
				),
				(array) $atts,
				'teamx_restrict'
			);

			$matches = $this->current_user_matches_plan_expression( (string) $atts['plan'] );
			$mode    = strtolower( trim( (string) $atts['mode'] ) );
			$show    = 'hide' === $mode ? ! $matches : $matches;

			if ( ! $show ) {
				return '';
			}

			return do_shortcode( (string) $content );
		}

		/**
		 * Check whether the current user's team has an active membership matching a plan expression.
		 *
		 * The expression supports OR with commas, AND with plus signs, and NOT with an exclamation mark.
		 * Example: 111,!333 means plan 111 OR not plan 333; 111+222 means plan 111 AND plan 222.
		 *
		 * @param string $expression Plan expression.
		 * @return bool
		 */
		private function current_user_matches_plan_expression( $expression ) {
			$team = $this->get_current_user_team();

			if ( ! $team || '' === trim( $expression ) ) {
				return false;
			}

			$active_plan_ids = $this->get_active_team_plan_ids( $team );

			foreach ( array_filter( array_map( 'trim', explode( ',', $expression ) ) ) as $or_group ) {
				$and_result = true;

				foreach ( array_filter( array_map( 'trim', explode( '+', $or_group ) ) ) as $token ) {
					$negated = 0 === strpos( $token, '!' );
					$plan_id = absint( $negated ? substr( $token, 1 ) : $token );

					if ( ! $plan_id ) {
						$and_result = false;
						break;
					}

					$has_plan    = in_array( $plan_id, $active_plan_ids, true );
					$and_result = $and_result && ( $negated ? ! $has_plan : $has_plan );
				}

				if ( $and_result ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Get the current user's single associated team, if they are an owner, manager, or member.
		 *
		 * @return object|false
		 */
		private function get_current_user_team() {
			$user_id = get_current_user_id();

			if ( ! $user_id || ! function_exists( 'wc_memberships_for_teams_get_teams' ) ) {
				return false;
			}

			$teams = wc_memberships_for_teams_get_teams(
				$user_id,
				array(
					'role'         => array( 'owner', 'manager', 'member' ),
					'posts_per_page' => 1,
				)
			);

			if ( is_array( $teams ) && ! empty( $teams ) ) {
				return reset( $teams );
			}

			return false;
		}

		/**
		 * Get a team display name.
		 *
		 * @param object $team Team object.
		 * @return string
		 */
		private function get_team_name( $team ) {
			if ( is_callable( array( $team, 'get_name' ) ) ) {
				return (string) $team->get_name();
			}

			if ( is_callable( array( $team, 'get_id' ) ) ) {
				return get_the_title( $team->get_id() );
			}

			return '';
		}

		/**
		 * Get active membership plan IDs for the team.
		 *
		 * @param object $team Team object.
		 * @return int[]
		 */
		private function get_active_team_plan_ids( $team ) {
			$plan_ids = array();

			if ( is_callable( array( $team, 'get_plan_id' ) ) && $this->team_has_active_membership( $team ) ) {
				$plan_ids[] = absint( $team->get_plan_id() );
			}

			foreach ( $this->get_team_user_memberships( $team ) as $user_membership ) {
				if ( ! $this->user_membership_is_active( $user_membership ) ) {
					continue;
				}

				if ( is_callable( array( $user_membership, 'get_plan_id' ) ) ) {
					$plan_ids[] = absint( $user_membership->get_plan_id() );
				}
			}

			return array_values( array_unique( array_filter( $plan_ids ) ) );
		}

		/**
		 * Determine whether the team itself reports an active membership status.
		 *
		 * @param object $team Team object.
		 * @return bool
		 */
		private function team_has_active_membership( $team ) {
			if ( is_callable( array( $team, 'has_active_membership' ) ) ) {
				return (bool) $team->has_active_membership();
			}

			if ( is_callable( array( $team, 'get_status' ) ) ) {
				return 'active' === (string) $team->get_status();
			}

			return true;
		}

		/**
		 * Get user memberships attached to the team.
		 *
		 * @param object $team Team object.
		 * @return array<int,object>
		 */
		private function get_team_user_memberships( $team ) {
			if ( is_callable( array( $team, 'get_user_memberships' ) ) ) {
				$memberships = $team->get_user_memberships();

				return is_array( $memberships ) ? $memberships : array();
			}

			if ( is_callable( array( $team, 'get_id' ) ) && function_exists( 'wc_memberships_get_user_memberships' ) ) {
				$memberships = wc_memberships_get_user_memberships(
					array(
						'team_id' => $team->get_id(),
					)
				);

				return is_array( $memberships ) ? $memberships : array();
			}

			return array();
		}

		/**
		 * Determine whether a user membership is active.
		 *
		 * @param object $user_membership User membership object.
		 * @return bool
		 */
		private function user_membership_is_active( $user_membership ) {
			if ( is_callable( array( $user_membership, 'is_active' ) ) ) {
				return (bool) $user_membership->is_active();
			}

			if ( is_callable( array( $user_membership, 'has_status' ) ) ) {
				return (bool) $user_membership->has_status( 'active' );
			}

			return false;
		}
	}
}

Aspen_Team_Seats_Expansion::instance();
