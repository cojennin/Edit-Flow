<?php

class EF_Dashboard_My_Posts_Widget {
	
	function __construct() {
		// Silence is golden
	}

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'my_posts_widgets_js' ) );
		add_action( 'wp_ajax_get-items-following', array( __CLASS__, 'myposts_get_items_following' ) );
	}

	public static function my_posts_widgets_js() {
		wp_enqueue_script( 'handlebars', plugins_url( 'common/js/handlebars-v1.1.2.js', EDIT_FLOW_FILE_PATH ) );
		wp_enqueue_script( 'backbone', plugins_url( 'common/js/backbone-min.js', EDIT_FLOW_FILE_PATH ) );
		wp_enqueue_script( 'dashboard-myposts', plugins_url( 'modules/dashboard/lib/dashboard-myposts.js', EDIT_FLOW_FILE_PATH ), array( 'handlebars', 'backbone' ), EDIT_FLOW_VERSION, true );
	}

	public static function myposts_get_items_following() {
		if( empty( $_POST['itemNonce'] ) )
			wp_send_json_error( 'Error: Nonce check failed.' );

		if( !wp_verify_nonce( $_POST['itemNonce'], 'ef-myposts-get-posts-following-action' ) )
			wp_send_json_error( 'Error: Nonce check failed.' );

		if( $_POST['itemType'] != 'comments' )
			$items_to_return = self::get_posts_following( $_POST['itemType'] );
		else
			$items_to_return = self::get_comments_following();

		wp_send_json_success( $items_to_return );
	}

	public static function get_posts_following( $post_type ) {
		global $edit_flow; 

		$myposts = $edit_flow->notifications->get_user_following_posts();
		$formatted_items = array();
		
		foreach( $myposts as $post ) {
			$item = new stdClass;
			$item->title = $post->post_title;
			$item->status = $post->post_status;
			$item->status_nice = $edit_flow->custom_status->get_post_status_friendly_name( $post->post_status );
			$item->date = $post->post_date;
			$item->timestamp = get_the_time( 'U', $post->ID );
			$item->human_time = human_time_diff( $item->timestamp );
			$item->user = get_the_author_meta( 'display_name', $post->post_author );
			$item->type = $post_type;
			$item->link = get_edit_post_link( $post->ID );

			$formatted_items[] = $item;
		}

		return $formatted_items;
	}

	public static function get_comments_following() {
		global $edit_flow, $wpdb;

		$user = (int) wp_get_current_user()->ID;
		$user = get_userdata($user)->user_login;

		//This is gonna be a hacked up query.
		//Need to find last 5 comments on posts we follow and whether or not it's a response
		$posts_following = get_term_by( 'slug', $user, $edit_flow->notifications->following_users_taxonomy );
		$following_comments = (array) $wpdb->get_results("
			SELECT * FROM $wpdb->comments
			INNER JOIN 
				(SELECT $wpdb->posts.ID FROM $wpdb->posts INNER JOIN $wpdb->term_relationships ON 
				($wpdb->posts.ID = $wpdb->term_relationships.object_id) 
				WHERE 1=1 
				AND ( $wpdb->term_relationships.term_taxonomy_id IN ($posts_following->term_id) ) 
				AND wp_posts.post_type = 'post' 
				AND (wp_posts.post_status <> 'trash' AND wp_posts.post_status <> 'auto-draft') 
				GROUP BY $wpdb->posts.ID 
				ORDER BY $wpdb->posts.post_modified 
				DESC ) 
			as post_notifications 
			ON $wpdb->comments.comment_post_ID = post_notifications.ID
			AND $wpdb->comments.comment_type = 'editorial-comment'
			ORDER BY comment_date 
			DESC 
			LIMIT 5;");

		$formatted_items = array();
		foreach( $following_comments as $comment ) {
			$item = new stdClass;
			$item->title = get_the_title( $comment->comment_post_ID );
			$item->date = $comment->comment_date;
			$item->timestamp = strtotime( $comment->comment_date );
			$item->human_time = human_time_diff( $item->timestamp );
			$item->user = $comment->comment_author;
			$item->link = get_edit_post_link( $comment->comment_post_ID ) . '#comment-' . $comment->comment_ID;
			$item->comment_content = strlen( $comment->comment_content ) < 140 ? $comment->comment_content : substr( $comment->comment_content, 0, 140 ) . '...';

			$formatted_items[] = $item;
		}

		return $formatted_items;
	}

	public static function myposts_widget() {
		global $edit_flow;
		$myposts = $edit_flow->notifications->get_user_following_posts();
	?>
		<div id="ef-myposts">
			<ul class="cf ef-myposts-content-type">
				<li><a href="#" class="active ef-myposts-button" data-type="posts">Posts</a></li>
				<li><a href="#" class="ef-myposts-button" data-type="comments">Comments</a></li>
				<li><a href="#" class="ef-myposts-refresh">Refresh</a></li>
			</ul>
			<ul class="ef-myposts-content-items cf">
				<?php if( empty( $myposts ) ): ?>
					<p>Sorry! You're not subscribed to any posts!</p>
				<?php endif; ?>

				<?php foreach( $myposts as $post ): ?>
					<li>
						<p><a class="ef-myposts-item-name" href="<?php echo get_edit_post_link( $post->ID ); ?>"><?php echo $post->post_title; ?></a></p>
						<span class="ef-post-status ef-status-<?php echo $post->post_status; ?>"><?php echo $edit_flow->custom_status->get_post_status_friendly_name( $post->post_status ); ?></span>
						<p class="ef-myposts-item-message"><?php if( $post->post_status == 'publish' ) { echo 'Published'; } else { echo 'Updated'; } ?> <?php echo human_time_diff( get_the_time( 'U', $post->ID ) ); ?> ago by <?php echo get_the_author_meta( 'display_name', $post->post_author ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php wp_nonce_field( 'ef-myposts-get-posts-following-action', 'ef-myposts-get-posts-following' ); ?>
		</div>

		<script type="text/x-handlebars-template" id="ef-myposts-posts-templ">
			{{#each content_items}}
				<li>
					<p><a class="ef-myposts-item-name" href="{{{link}}}">{{title}}</a></p>
					<span class="ef-post-status ef-status-{{status}}">{{status_nice}}</span>
					<p class="ef-myposts-item-message">{{post_message this}}</p>
				</li>
			{{else}}
			<p>Sorry! You're not subscribed to any posts!</p>
			{{/each}}
		</script>

		<script type="text/x-handlebars-template" id="ef-myposts-comments-templ">
			{{#each content_items}}
				<li class="ef-myposts-comment-item">
					<p class="ef-myposts-comment-on">Comment on <a class="ef-myposts-item-name" href="{{{link}}}"><span class="ef-myposts-post-title">{{title}}</span></a></p>
					<span class="ef-myposts-comment-info">
						{{#if response_to}}
							In response to {{response_to}}, {{human_time}} ago:</span>
						{{else}}
							Posted {{human_time}} ago by {{user}} </span>
						{{/if}}
					</span>
					<p class="ef-myposts-comment">{{comment_content}}</p>
				</li>
			{{else}}
				<p>Sorry! No recent comment activity.</p>
			{{/each}}
		</script>
	<?php
	}

	/**
	 * Creates My Posts widget
	 * Shows a list of the "posts you're following" sorted by most recent activity.
	 */ 
	function __myposts_widget() {
		global $edit_flow;

		$myposts = $edit_flow->notifications->get_user_following_posts();
		
		?>
		<div class="ef-myposts">
			<?php if( !empty($myposts) ) : ?>
				
				<?php foreach( $myposts as $post ) : ?>
					<?php
					$url = esc_url(get_edit_post_link( $post->ID ));
					$title = esc_html($post->post_title);
					?>
					<li>
						<h4><a href="<?php echo $url ?>" title="<?php _e('Edit this post', 'edit-flow') ?>"><?php echo $title; ?></a></h4>
						<span class="ef-myposts-timestamp"><?php _e('This post was last updated on', 'edit-flow') ?> <?php echo get_the_time('F j, Y \\a\\t g:i a', $post) ?></span>
					</li>	
				<?php endforeach; ?>
			<?php else : ?>
				<p><?php _e('Sorry! You\'re not subscribed to any posts!', 'edit-flow') ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}