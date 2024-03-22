<?php
/**
 * Generic handler.
 *
 * This contains the default Account handlers.
 *
 * @package Enable_Mastodon_Apps
 */

namespace Enable_Mastodon_Apps\Handler;

use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Mastodon_App;

/**
 * This is the generic handler to provide needed helper functions.
 */
class Handler {
	protected function get_posts_query_args( $request ) {
		$limit = $request->get_param( 'limit' );
		if ( $limit < 1 ) {
			$limit = 20;
		}

		$args = array(
			'posts_per_page'   => $limit,
			'post_type'        => array( 'post' ),
			'suppress_filters' => false,
			'post_status'      => array( 'publish', 'private' ),
		);

		$pinned = $request->get_param( 'pinned' );
		if ( $pinned || 'true' === $pinned ) {
			$args['pinned'] = true;
			$args['post__in'] = get_option( 'sticky_posts' );
			if ( empty( $args['post__in'] ) ) {
				// No pinned posts, we need to find nothing.
				$args['post__in'] = array( -1 );
			}
		}

		$app = Mastodon_App::get_current_app();
		if ( $app ) {
			$args = $app->modify_wp_query_args( $args );
		} else {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-status' ),
				),
			);
		}

		$post_id = $request->get_param( 'post_id' );
		if ( $post_id ) {
			$args['p'] = $post_id;
		}

		return apply_filters( 'enable_mastodon_apps_get_posts_query_args', $args, $request );
	}

	protected function get_posts( $args, $min_id = null, $max_id = null ): \WP_REST_Response {
		if ( $min_id ) {
			$min_filter_handler = function ( $where ) use ( $min_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $min_id );
			};
			$args['order'] = 'ASC';
			add_filter( 'posts_where', $min_filter_handler );
		}

		if ( $max_id ) {
			$max_filter_handler = function ( $where ) use ( $max_id ) {
				global $wpdb;
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $max_id );
			};
			add_filter( 'posts_where', $max_filter_handler );
		}

		$posts = get_posts( $args );

		if ( $min_id ) {
			remove_filter( 'posts_where', $min_filter_handler );
		}
		if ( $max_id ) {
			remove_filter( 'posts_where', $max_filter_handler );
		}

		$k = 0;
		$statuses = array();
		foreach ( $posts as $post ) {
			/**
			 * Modify the status data.
			 *
			 * @param array|null $account The status data.
			 * @param int $post_id The object ID to get the status from.
			 * @param array $data Additional status data.
			 * @return array|null The modified status data.
			 */
			$status = apply_filters( 'mastodon_api_status', null, $post->ID, array() );

			if ( $status && ! is_wp_error( $status ) ) {
				$statuses[ $post->post_date . '.' . ++$k ] = $status;
			}
		}
		if ( ! isset( $args['pinned'] ) || ! $args['pinned'] ) {
			// Comments cannot be pinned for now.
			$comments = get_comments(
				array(
					'meta_key'   => 'protocol',
					'meta_value' => 'activitypub',
				)
			);

			foreach ( $comments as $comment ) {
				$post_id = $this->remap_comment_id( $comment->comment_ID );

				/**
				 * Modify the status data.
				 *
				 * @param array|null $account The status data.
				 * @param int $post_id The object ID to get the status from.
					 * @param array $data Additional status data.
				 * @return array|null The modified status data.
				 */
				$status = apply_filters(
					'mastodon_api_status',
					null,
					$post_id,
					array(
						'in_reply_to_id' => $comment->comment_post_ID,
					)
				);

				if ( $status && ! is_wp_error( $status ) ) {
					$statuses[ $comment->comment_date . '.' . ++$k ] = $status;
				}
			}
		}
		krsort( $statuses );

		$response = new \WP_REST_Response( array_values( $statuses ) );
		if ( ! empty( $statuses ) ) {
			$response->add_link( 'next', remove_query_arg( 'min_id', add_query_arg( 'max_id', end( $statuses )->id, home_url( $_SERVER['REQUEST_URI'] ) ) ) );
			$response->add_link( 'prev', remove_query_arg( 'max_id', add_query_arg( 'min_id', reset( $statuses )->id, home_url( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		return $response;
	}

	public static function get_mastodon_language( $lang ) {
		if ( false === strpos( $lang, '_' ) ) {
			return $lang . '_' . strtoupper( $lang );
		}
		return $lang;
	}
}
