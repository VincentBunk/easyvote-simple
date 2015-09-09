<?php

/*
Plugin Name: Easyvote Simple
Plugin URI: http://www.stadtkreation.de/wordpress.php
Description: Simple Voting Plugin for Stadtkreation Theme Family
Version: 0.3
Author: André Duarte, Kai Förstermann, Johannes Bouchain
Text Domain: easyvote-simple
Domain Path: /languages/
Author URI: http://www.stadtkreation.de/wordpress.php
*/
__('Simple Voting Plugin for Stadtkreation Theme Family','easyvote-simple');

// plugin textdomain definition
function easyvote_simple_textdomain() {
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain( 'easyvote-simple', false, $plugin_dir.'/languages/' );
}
add_action('plugins_loaded', 'easyvote_simple_textdomain');

// create dashboard submenu
function easyvote_simple_create_menu() {
	//create new sub menu
	add_options_page(__('Easyvote Simple','easyvote-simple'),__('Easyvote Simple','easyvote-simple'), 'manage_options', 'easyvote-simple-preferences', 'easyvote_simple_settings_page');
	
	//call register settings function
	add_action( 'admin_init', 'easyvote_simple_register_settings' );
}
add_action('admin_menu', 'easyvote_simple_create_menu');

// create settings page for the plugin
function easyvote_simple_settings_page() {
	echo '<div class="wrap">'."\n";
	echo '<h2>'.__('Preferences for Easyvote Simple plugin','easyvote-simple').'</h2>'."\n";
	echo '<form method="post" action="options.php">'."\n\t";
	settings_fields('easyvote-simple-settings-group' );
	do_settings_sections('easyvote-simple-settings-group');
	
	// checkbox: only logged-in users can vote yes/no
	echo '<p><label for="easyvote-simple-must-register">'.__('User must be logged-in to vote','easyvote-simple').'</label> <input type="checkbox" id="easyvote-simple-must-register"'.(get_option('easyvote-simple-must-register') == 'on' ? ' checked="checked"' : '').' name="easyvote-simple-must-register" /></p>';
	
	
	echo '<p class="submit"><input type="submit" class="button-primary" value="'.__('Save Changes').'" /></p>'."\n";
	echo '</form>'."\n";
	echo '</div>'."\n";
}

add_option('easyvote-simple-must-register');
// register settings for the plugin
function easyvote_simple_register_settings() {
	register_setting('easyvote-simple-settings-group','easyvote-simple-must-register');
}

// enqueue css stylesheet for the plugin
function easyvote_simple_scripts() {
	$plugin_dir = basename(dirname(__FILE__));
	wp_enqueue_style('easyvote-simple-iconfont', plugins_url('', __FILE__).'/fontawesome/fontawesome.css');
	wp_enqueue_style('easyvote-simple-style', plugins_url('', __FILE__).'/easyvote-simple.css');
}
add_action( 'wp_enqueue_scripts', 'easyvote_simple_scripts' );

// add voting functionality to content
function easyvote_content_output($content) {
	global $post, $current_user;
	get_currentuserinfo();
	
	// get votes for post from post meta fields (from serialized data)
	
	$votes=get_post_meta($post->ID,'easyvote-simple-votingdata',true);
	$votes_array=unserialize($votes);
	
	if(!is_array($votes_array['ID']) || !in_array($current_user->ID,$votes_array['ID'])) {
		$can_support=true;
		
		// update vote meta value for post when voting has been sent
		if($_POST['easyvote-simple-vote']==1) {
			$votes_array['ID'][]=$current_user->ID;
			$timestamp=time();
			$votes_array['votedate'][]=date("Y-m-d H:i:s",$timestamp);
			$votes=serialize($votes_array);
			update_post_meta($post->ID,'easyvote-simple-votingdata',$votes);
			$lapkarte_votingdata=unserialize(get_post_meta($post->ID,'easyvote-simple-votingdata',true));
			update_post_meta($post->ID,'easyvote-simple-sumvotes',sizeof($lapkarte_votingdata['ID']));
			$can_support=false;

			// send e-mail notification to WP admin
			$to = get_settings('admin_email');
			$headers = 'From: '.get_settings('blogname').' <noreply@'.easyvote_simple_maildomain().'>' . "\r\n";
			$subject = '['.get_settings('blogname').'] '.__('New vote for an idea','easyvote-simple');
			$message = sprintf(__('A new vote has been added to an idea at your site %s.','easyvote-simple'),get_settings('blogname'))."\r\n\r\n";
			$message .= __('Username','easyvote-simple').': '.$current_user->user_login . "\r\n";
			$message .= __('E-mail address','easyvote-simple').': '. $current_user->user_email . "\r\n";
			$message .= __('Title of the idea that has been voted','easyvote-simple').': '.get_the_title($post->ID). "\r\n\r\n";
			$message .= __('View the idea','easyvote-simple').': '."\r\n".get_permalink($current_post_id)."\r\n\r\n";
			wp_mail($to,$subject,$message,$headers);
		}
	}	

	// output voting button (inactive for not logged in users)
	if(is_single() && get_post_type($post->ID)=='userpost') {
		$list_votes=unserialize(get_post_meta($post->ID,'easyvote-simple-votingdata',true));
		$sum_votes=sizeof($list_votes['ID']);
		
		// create translatable text variables
		if($sum_votes>0) $extra=__('already','easyvote-simple');
		//$need_login_text = sprintf(__('Do you want to vote for this proposal? Then you only have to <a href="%s">login</a>!','easyvote-simple'),wp_login_url().'?redirect_to='.get_permalink($post->ID));
		$count_votes_text = sprintf(_x('This idea %1$s has %2$s %3$s','This idea (already) has X vote(s)','easyvote-simple'),$extra, $sum_votes, ($sum_votes == 1 ? _x('vote','output number of votes, singular','easyvote-simple') : _x('votes','output number of votes, plural','easyvote-simple')));
		//$need_register_text = sprintf(__('If you don\'t have an account yet, you can <a href="%s">create a user account here</a>.','easyvote-simple'),wp_login_url().'?action=register');
		
		if(!is_user_logged_in()) $button='<p><a href="'.wp_login_url().'?redirect_to='.get_permalink($post->ID).'" class="support-button inactive">'.__('Please login to vote for this idea','easyvote-simple').'</a> '.$count_votes_text.'</strong>.</p>'; //<p>'.$need_login_text.'<br /><small>'.$need_register_text.'</small></p>';
		else {
			if($can_support) $button='<form action="" method="post"><p><input type="hidden" name="easyvote-simple-vote" value="1" /><button class="form-control support-button" type="submit">'.__('Vote for this idea','easyvote-simple').'</button> '.$count_votes_text.'</strong>.</p></form>';
			else $button='<p><span class="support-button inactive">'.__('You already voted for this idea','easyvote-simple').'</span> '.$count_votes_text.'</strong>.</p></form>';
		}
		$content=$button.$content.$button;
	}
	return $content;
}
add_filter('the_content','easyvote_content_output');

// create widget for most voted ideas
function widget_most_voted() {
	global $post,$wpdb;
	$args = array('post_type' => 'post','posts_per_page' => -1);
	$myposts = query_posts( $args );
	
	foreach($myposts as $single_post) {
		$lapkarte_votingdata=unserialize(get_post_meta($single_post->ID,'easyvote-simple-votingdata',true));
		update_post_meta($single_post->ID,'easyvote-simple-sumvotes',sizeof($lapkarte_votingdata['ID']));
	}
	
	$querystr = "
	SELECT wposts.* FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
	WHERE wposts.ID = wpostmeta.post_id 
	AND wpostmeta.meta_key = 'easyvote-simple-sumvotes' 
	AND wposts.post_type = 'post' 
	ORDER BY ABS(wpostmeta.meta_value) DESC
	LIMIT 5
	";
	$myposts = $wpdb->get_results($querystr);
	$output = '';
	$output .= '<li class="widgetcontainer"><h3 class="widgettitle">'.__('Most voted ideas','easyvote-simple').'</h3><ul>';
	
	foreach($myposts as $single_post) {
		$output .= '<li class="highlighted"><a href="'.get_permalink($single_post->ID).'">'.$single_post->post_title.'</a><br />';
		$output .= get_post_meta($single_post->ID,'easyvote-simple-sumvotes',true);
		if(get_post_meta($single_post->ID,'easyvote-simple-sumvotes',true)==1) $output .= ' '._x('vote','output number of votes, singular','easyvote-simple');
		else $output .= ' '._x('votes','output number of votes, plural','easyvote-simple');
		$output .= '</li>';
	}
	$output .= '</ul></li>';
	return $output;
}

// register the widget
function widget_most_voted_init() {
  register_sidebar_widget(__('Most voted'), 'widget_most_voted');
}
add_action("plugins_loaded", "widget_most_voted_init");

function easyvote_simple_maildomain() {
	$maildomain = str_replace('http://','',get_bloginfo('url'));
	$maildomain = str_replace('www.','',$maildomain);
	$maildomain = explode('/',$maildomain);
	$maildomain = $maildomain[0];
	return $maildomain;
}

?>