<?php
/**
 * Lemmy Integration
 *
 * Add what it takes to make Lemmy apps talk to WordPress.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Integration;

/**
 * This is the class that implements the Lemmy adapations.
 *
 * @since 0.1
 *
 * @package Enable_Mastodon_Apps
 * @author Alex Kirk
 */
class Lemmy {
	public $api;
	public function __construct() {
		add_filter( 'mastodon_api_generic_routes', array( $this, 'register_api_routes' ) );
		add_action( 'mastodon_register_rest_routes', array( $this, 'add_rest_routes' ), 10, 2 );
	}

	public function register_api_routes( $routes ) {
		$routes[] = 'api/v3/site';
		return $routes;
	}

	public function add_rest_routes( \Enable_Mastodon_Apps\Mastodon_API $api ) {
		register_rest_route(
			$api::PREFIX,
			'api/v3/site',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_site' ),
				'permission_callback' => array( $api, 'public_api_permission' ),
			)
		);
	}

	public function api_site( $request ) {
		$creation_date = gmdate( 'Y-m-d\TH:i:s.000P' );
		$response = array(
			'site_view' => array(
				'site'                  => array(
					'id'          => 1,
					'name'        => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'sidebar'     => '',
					'published'   => $creation_date,
					'updated'     => $creation_date,
					'description' => html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
					'actor_id'    => home_url(),
				),
				'local_site'            => array(
					'id'                            => 1,
					'site_id'                       => 1,
					'site_setup'                    => false, // ?
					'enable_downvotes'              => true,
					'enable_nsfw'                   => true,
					'community_creation_admin_only' => false,
					'require_email_verification'    => false,
					'application_question'          => '',
					'private_instance'              => false,
					'default_theme'                 => 'browser',
					'default_post_listing_type'     => 'Local',
					'hide_modlog_mod_names'         => true,
					'application_email_admins'      => false,
					'slur_filter_regex'             => '',
					'actor_name_max_length'         => 20,
					'federation_enabled'            => true,
					'captcha_enabled'               => false,
					'captcha_difficulty'            => 'medium',
					'published'                     => $creation_date,
					'updated'                       => $creation_date,
					'registration_mode'             => 'RequireApplication',
					'reports_email_admins'          => false,
					'federation_signed_fetch'       => false,
				),
				'local_site_rate_limit' => array(
					'local_site_id'                   => 1,
					'message'                         => 180,
					'message_per_second'              => 60,
					'post'                            => 6,
					'post_per_second'                 => 600,
					'register'                        => 3,
					'register_per_second'             => 3600,
					'image'                           => 6,
					'image_per_second'                => 3600,
					'comment'                         => 6,
					'comment_per_second'              => 600,
					'search'                          => 60,
					'search_per_second'               => 600,
					'published'                       => $creation_date,
					'import_user_settings'            => 1,
					'import_user_settings_per_second' => 86400,
				),
				'counts'                => array(
					'site_id'                => 1,
					'users'                  => 0,
					'posts'                  => 0,
					'comments'               => 0,
					'communities'            => 0,
					'users_active_day'       => 0,
					'users_active_week'      => 0,
					'users_active_month'     => 0,
					'users_active_half_year' => 0,
				),
				'admins'                => array(),
				'version'               => '0.19.3',
				'all_languages'         => array(
					array(
						'id'   => 0,
						'code' => 'und',
						'name' => 'Undetermined',
					),

				),
			),
		);

		return $response;
	}
}
