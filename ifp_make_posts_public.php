<?php
/*
Plugin Name: IFP Make Posts Public
Plugin URI: http://www.vitaminmlabs.com
Description: This plugin will iterate through existing members-only posts, making them public after a given amount of time has passed. Currently it only searches for audio and video posts.
Version: 0.9
Author: Michael Medaglia
Author URI: http://www.vitaminmlabs.com
License: GPL2
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

// More info http://codex.wordpress.org/Function_Reference/wp_schedule_event
//require_once("../wp-blog-header.php");

register_activation_hook(__FILE__, array('ifpMakePostsPublic', 'mpp_activation'));
add_action('makePostsPublicEvent', array('ifpMakePostsPublic', 'iterate'));

register_deactivation_hook(__FILE__, array('ifpMakePostsPublic', 'mpp_deactivation'));



class ifpMakePostsPublic{
	const MPP_DEBUG = false;
	
	// Remove the scheduled event
	function mpp_deactivation() {
		wp_clear_scheduled_hook('makePostsPublicEvent');
	}
	
	// Create the scheduled event
	function mpp_activation() {
		wp_schedule_event(time(), 'daily', 'makePostsPublicEvent');
	}
	
	// Filter function that will add our where clause to the query
	function filter_where( $where = '' ) {
		$days = 180; //Number of days ago to filter
		$today = date('Y-m-d h:m', time());
		$daysAgo = date('Y-m-d', strtotime("-$days days"));	
		$where .= " AND post_date < '$daysAgo'";

		ifpMakePostsPublic::debug($where);
		return $where;
	}
	
	function iterate() {
		// Select only members-only posts. This won't work until we update to WP 3.1 :(
		$metaArgs = array(
			array(
				'key'=>'_role',
				'value'=>'member',
				'compare'=>'!=',
				)
			);
		
		// $audioCatId = 82; //Podcasts
		// $videoCatId = 83; //Video
		// $queryArgs = array(
			// 'category__in' => array( $audioCatId, $videoCatId ),
			// 'post_status' => 'publish',
			// 'nopaging' => true,
			// );
    
    // new query to handle taxonomy for video and audio
    $queryArgs = array('tax_query' => 
                                    array('relationship' => 'AND',
                                    array('taxonomy' => 'type',
                                          'field' => 'slug',
                                          'terms' => array('video', 'audio')
                                          )
                                        ),
                        'post_status' => 'publish',
                        'nopaging' => true,
                        );
		
		// Run the query
		add_filter( 'posts_where', array('ifpMakePostsPublic','filter_where'));	
		
		$query = new WP_Query($queryArgs);
		
		//Don't forget to remove the filter!
		remove_filter('posts_where', array('ifpMakePostsPublic','filter_where')); 
		
		global $post;
		while($query->have_posts()) : $query->the_post();
		
			ifpMakePostsPublic::debug($post->post_title);
			$meta = get_post_meta($post->ID);
			if($meta && isset($meta['_role']) && in_array('member',$meta['_role'])){
				//Delete the custom field to set the post to public
				delete_post_meta($post->ID, '_role');
				ifpMakePostsPublic::debug("Group:" . $meta['_role'][0]);				
			}
			
			//Made a mistake? Add the custom fields back
			//add_post_meta($post->ID, '_role','member',true);
			ifpMakePostsPublic::debug("<hr/>\n");
			
		endwhile;
	}
	
	function debug($s){
		if(ifpMakePostsPublic::MPP_DEBUG){
			print_r($s);
			echo("<br/>\n");
		}
	}

}

?>