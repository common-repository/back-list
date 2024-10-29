<?php
/*
Plugin Name: Back List
Plugin URI: http://w3prodigy.com/wordpress-plugins/back-list/
Description: Adds Whitelist and Blacklist options for Trackbacks and Pingbacks as well as the option to auto-accept Trackbacks from your own blog. These options can be found on the Discussion Options page.
Author: Jay Fortner
Author URI: http://w3prodigy.com
Version: 0.5
Tags: anti-spam, blacklist, comments, plugin, security, seo, spam, trackbacks, pingbacks, whitelist
License: GPL2
*/

new Back_List;

/**
 * Back List
 * @since 0.1
 * @uses Back_List_Options
 * 
 * This object uses the following options:
 * 1. Automatically approve if Trackback or Pingback is from this blog
 * 2. Trackback and Pingback Whitelist
 * 3. Trackback and Pingback Blacklist
 */
class Back_List {
	
	/**
	 * Controller
	 *
	 * Instantiates the Back_List_Options object
	 * Add preprocess_comment filter for new comments
	 */
	function Back_List()
	{
		new Back_List_Options;
		add_filter( 'preprocess_comment', array( &$this, 'preprocess_comment' ) );
	} // function
	
	/**
	 * Check Pingbacks and Trackbacks against the lists
	 *
	 * This filter is applied every time a comment is added
	 */
	function preprocess_comment( $commentdata = null )
	{
		if( 'pingback' == $commentdata['comment_type'] || 'trackback' == $commentdata['comment_type'] ):
		
			if( preg_match( $pattern = '@^(?:http://)(?:www.)?([^/]+)@i', $subject = $commentdata['comment_author_url'], $matches ) )
				$host = $matches[1];
				
			$ip = $commentdata['comment_author_IP'];
			
			# 1. Automatically approve if Trackback or Pingback is from this blog
			if( $from_blog = get_option( 'back_list_blog' ) ):
				if( preg_match( $pattern = '@^(?:http://)(?:www.)?([^/]+)@i', $subject = get_bloginfo( 'url' ), $matches ) ):
					$this_host = $matches[1];
					
					if( $host == $this_host ):
						$commentdata['comment_approved'] = 1;
						return $commentdata;
					endif;
					
				endif;
			endif;
			
			# 2. Trackback and Pingback Whitelist
			if( $this->in_list( $host, 'white' ) || $this->in_list( $ip, 'white' ) ):
				$commentdata['comment_approved'] = 1;
				return $commentdata;
			endif;
			
			# 3. Trackback and Pingback Blacklist
			if( $this->in_list( $host, 'black' ) || $this->in_list( $ip, 'black' ) ):
				header("HTTP/1.0 404 Not Found");
				die();
			endif;
				
		endif;
		
		return $commentdata;
	} // function
	
	/**
	 * Determines if host name defined by $needle is in one of our lists defined by $list_type
	 * @param (str) $needle - string to be checked
	 * @param (str) $list_type - black or white
	 */
	function in_list( $needle = null, $list_type = null )
	{
		if( is_null( $needle ) ):
			trigger_error( 'First argument can not be null', E_USER_WARNING );
			return false;
		endif;
		
		if( is_null( $list_type ) ):
			trigger_error( 'Second argument can not be null', E_USER_WARNING );
			return false;
		endif;
		
		if( $list = get_option( "back_list_$list_type" ) ):
			$list_items = $this->get_list_items( $list );
			if( in_array( $needle, $haystack = $list_items ) )
				return true;
			
			# checks domain.com of sub.domain.com	
			if( preg_match( $pattern = "@^(?:[^\.]+).(.*)@i", $subject = $needle, $matches ) ):
				if( in_array( $matches[1], $haystack = $list_items ) )
					return true;
			endif;
			
		endif;
		
		return false;
	} // function
	
	/**
	 * Create an array from new line separated lists
	 */
	function get_list_items( $list = null )
	{
		if( is_null( $list ) ):
			trigger_error( 'First argument can not be null', E_USER_WARNING );
			return false;
		endif;
		
		$search = array(
			"\r\n",
			"\r"
			);
		$list = str_replace( $search, $replace = "\n", $subject = $list );
		
		return explode( "\n", $list );
	} // function
	
} // class

/**
 * Back List Options
 * @since 0.1
 *
 * This object adds the following options to the discussions page:
 * 1. Automatically approve if Trackback or Pingback is from this blog
 * 2. Trackback and Pingback Whitelist
 * 3. Trackback and Pingback Blacklist
 */
class Back_List_Options extends Back_List {
	
	/**
	 * Controller
	 *
	 * Add admin_init action for every administrative page load
	 */
	function Back_List_Options()
	{
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_filter( 'comment_row_actions', array( &$this, 'comment_row_actions' ), 10, 2 );
	} // function
	
	/**
	 * Register settings and add our fields to the Discussion settings page
	 *
	 * This action is called on every administrative page load
	 */
	function admin_init()
	{
		# 2. Trackback and Pingback Whitelist -> comment_row_actions
		if( !empty( $_GET['action'] ) && 'back-list-white' == $_GET['action'] && !empty( $_GET['host'] ) ):
			$comment_id = absint( $_GET['c'] );
		
			check_admin_referer( 'back-list-white-comment_' . $comment_id );
		
			$back_list_white = get_option( 'back_list_white' );

			if( empty( $back_list_white ) )
				$back_list_white = $_GET['host'];
			else
				$back_list_white .= "\n" . $_GET['host'];
			
			update_option( 'back_list_white', $back_list_white );
			wp_set_comment_status( $comment_id, 'approve' );
			wp_redirect( get_bloginfo( 'url' ) . "/wp-admin/edit-comments.php" );
		endif;
		
		# 3. Trackback and Pingback Blacklist -> comment_row_actions
		if( !empty( $_GET['action'] ) && 'back-list-black' == $_GET['action'] && !empty( $_GET['host'] ) ):
			$comment_id = absint( $_GET['c'] );
		
			check_admin_referer( 'back-list-black-comment_' . $comment_id );
		
			$back_list_black = get_option( 'back_list_black' );

			if( empty( $back_list_black ) )
				$back_list_black = $_GET['host'];
			else
				$back_list_black .= "\n" . $_GET['host'];
			
			update_option( 'back_list_black', $back_list_black );
			wp_delete_comment( $comment_id );
			wp_redirect( get_bloginfo( 'url' ) . "/wp-admin/edit-comments.php" );
		endif;
		
		# 1. Automatically approve if Trackback or Pingback is from this blog
		add_settings_field( 
			$id = 'back_list_blog',
			$title = 'My Trackbacks and Pingbacks',
			$callback = array( &$this, 'back_list_blog' ),
			$page = 'discussion'
			);
		register_setting( $option_group = 'discussion', $option_name = 'back_list_blog' );
			
		# 2. Trackback and Pingback Whitelist
		add_settings_field(
			$id = 'back_list_white',
			$title = 'Trackback and Pingback Whitelist',
			$callback = array( &$this, 'back_list_white' ),
			$page = 'discussion'
			);
		register_setting( $option_group = 'discussion', $option_name = 'back_list_white' );
		
		# 3. Trackback and Pingback Blacklist
		add_settings_field(
			$id = 'back_list_black',
			$title = 'Trackback and Pingback Blacklist',
			$callback = array( &$this, 'back_list_black' ),
			$page = 'discussion'
			);
		register_setting( $option_group = 'discussion', $option_name = 'back_list_black' );
	} // function
	
	/**
	 * Option Field - 1. Automatically approve if Trackback or Pingback is from this blog
	 */
	function back_list_blog()
	{
		$value = get_option('back_list_blog');
		
		$checked = '';
		if( !empty( $value ) )
			$checked = "checked='checked'";
		
		echo "<input type='checkbox' name='back_list_blog' id='back_list_blog' value='true' class='code' $checked /> Automatically approve Trackbacks and Pingbacks from this blog";
	} // function
	
	/**
	 * Option Field - 2. Trackback and Pingback Whitelist
	 */
	function back_list_white()
	{
		$value = get_option('back_list_white');
		
		echo "<p><label for='back_list_white'>When a Trackback or Pingback comes from any of these domains it will be marked as <strong>approved</strong>. One domain per line.</label></p>";
		echo "<textarea name='back_list_white' rows='10' cols='50' id='back_list_white' class='large-text code'>$value</textarea>";
	} // function
	
	/**
	 * Option Field - 3. Trackback and Pingback Blacklist
	 */
	function back_list_black()
	{
		$value = get_option('back_list_black');
		
		echo "<p><label for='back_list_white'>When a Trackback or Pingback comes from any of these domains it will be <strong>ignored</strong>. One domain per line.</label></p>";
		echo "<textarea name='back_list_black' rows='10' cols='50' id='back_list_black' class='large-text code'>$value</textarea>";
	} // function
	
	/**
	 * Add the White List and Black List options to each comment
	 */
	function comment_row_actions( $actions, $comment )
	{
		if( 'pingback' == $comment->comment_type || 'trackback' == $comment->comment_type ):
			$comment_author_url = $comment->comment_author_url;
			
			if( false === stripos( $haystack = $comment_author_url, $needle = 'http://' ) )
				$comment_author_url = "http://$comment_author_url";
			
			if( preg_match( $pattern = '@^(?:http://)(?:www.)?([^/]+)@i', $subject = $comment_author_url, $matches ) )
				$host = $matches[1];
		
			if( empty( $comment_author_url ) || empty( $host ) )
				return $actions;
		
			if( false === $this->in_list( $host, 'white' ) && false === $this->in_list( $host, 'black' ) ):
				$back_list_white_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "back-list-white-comment_$comment->comment_ID" ) );
				$back_list_white_url = get_bloginfo( 'url' ) . "/wp-admin/edit-comments.php?action=back-list-white&c=$comment->comment_ID&host=$host&$back_list_white_nonce";
				$actions['back-list-white'] = "<a href='$back_list_white_url'>White List $host</a>";
			
				$back_list_black_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "back-list-black-comment_$comment->comment_ID" ) );
				$back_list_black_url = get_bloginfo( 'url' ) . "/wp-admin/edit-comments.php?action=back-list-black&c=$comment->comment_ID&host=$host&$back_list_black_nonce";
				$actions['back-list-black'] = "<a href='$back_list_black_url' class='delete' style='color:#BC0B0B'>Black List $host</a>";
			endif;
		
		endif;
		
		return $actions;
	} // function
	
} // class