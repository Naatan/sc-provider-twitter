<?php

/*
Plugin Name: Social Connect - Twitter Gateway
Plugin URI: http://wordpress.org/extend/plugins/social-connect/
Description: Allows you to login / register with Twitter - REQUIRES Social Connect plugin
Version: 0.10
Author: Brent Shepherd, Nathan Rijksen
Author URI: http://wordpress.org/extend/plugins/social-connect/
License: GPL2
 */

require_once(dirname(__FILE__) . '/EpiCurl.php' );
require_once(dirname(__FILE__) . '/EpiOAuth.php' );
require_once(dirname(__FILE__) . '/EpiTwitter.php' );

class SC_Gateway_Twitter
{
	
	protected static $calls = array('connect','callback');
	
	static function init()
	{
		add_action('admin_init', 						array('SC_Gateway_Twitter', 'register_settings') );
		add_action('social_connect_button_list',		array('SC_Gateway_Twitter','render_button'));
		
		add_filter('social_connect_enable_options_page', create_function('$bool','return true;'));
		add_action('social_connect_options',			array('SC_Gateway_Twitter', 'render_options') );
	}
	
	static function call()
	{
		if ( !isset($_GET['call']) OR !in_array($_GET['call'], array('connect','callback')))
		{
			return;
		}
		
		call_user_func(array('SC_Gateway_Twitter', $_GET['call']));
	}
	
	static function register_settings()
	{
		register_setting( 'social-connect-settings-group', 'social_connect_twitter_consumer_key' );
		register_setting( 'social-connect-settings-group', 'social_connect_twitter_consumer_secret' );
	}
	
	static function render_options()
	{
		?>
		<h3><?php _e('Twitter Settings', 'social_connect'); ?></h3>
		<p><?php _e('To offer login via Twitter, you need to register your site as a Twitter Application and get a <strong>Consumer Key</strong>, a <strong>Consumer Secret</strong>, an <strong>Access Token</strong> and an <strong>Access Token Secret</strong>.', 'social_connect'); ?></p>
		<p><?php printf(__('Already registered? Find your keys in your <a target="_blank" href="%2$s">%1$s Application List</a>', 'social_connect'), 'Twitter', 'https://dev.twitter.com/apps'); ?></p>
		<p><?php printf(__('Need to register? <a href="%1$s">Register an Application</a> and fill the form with the details below:', 'social_connect'), 'http://dev.twitter.com/apps/new'); ?>
		<ol>
			<li><?php _e('Application Type: <strong>Browser</strong>', 'social_connect'); ?></li>
			<li><?php printf(__('Callback URL: <strong>%1$s</strong>', 'social_connect'), SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=callback&gateway=twitter'); ?></li>
			<li><?php _e('Default Access: <strong>Read &amp; Write</strong>', 'social_connect'); ?></li>
		</ol>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Consumer Key', 'social_connect'); ?></th>
				<td><input type="text" name="social_connect_twitter_consumer_key" value="<?php echo get_option('social_connect_twitter_consumer_key' ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Consumer Secret', 'social_connect'); ?></th>
				<td><input type="text" name="social_connect_twitter_consumer_secret" value="<?php echo get_option('social_connect_twitter_consumer_secret' ); ?>" /></td>
			</tr>
		</table>
		<?php
	}
	
	static function render_button()
	{
		$image_url = plugins_url() . '/' . basename( dirname( __FILE__ )) . '/button.png';
		?>
		<a href="javascript:void(0);" title="Twitter" class="social_connect_login_twitter"><img alt="Twitter" src="<?php echo $image_url ?>" /></a>
		<div id="social_connect_twitter_auth" style="display: none;">
			<input type="hidden" name="redirect_uri" value="<?php echo( SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=connect&gateway=twitter' ); ?>" />
		</div>
		
		<script type="text/javascript">
		(jQuery(function($) {
			var _do_twitter_connect = function() {
				var twitter_auth = $('#social_connect_twitter_auth');
				var redirect_uri = twitter_auth.find('input[type=hidden][name=redirect_uri]').val();
				window.open(redirect_uri,'','scrollbars=no,menubar=no,height=400,width=800,resizable=yes,toolbar=no,status=no');
			};
			
			$(".social_connect_login_twitter, .social_connect_login_continue_twitter").click(function() {
				_do_twitter_connect();
			});
		}));
		</script>
		<?php
	}
	
	static function connect()
	{
		$twitter_enabled 	= get_option('social_connect_twitter_enabled');
		$consumer_key 		= get_option('social_connect_twitter_consumer_key');
		$consumer_secret 	= get_option('social_connect_twitter_consumer_secret');
		
		if ($twitter_enabled && $consumer_key && $consumer_secret)
		{
			$twitter_api = new EpiTwitter($consumer_key, $consumer_secret);
			wp_redirect($twitter_api->getAuthenticateUrl());
			exit();
		}
		
		echo '<p>Social Connect plugin has not been configured for Twitter</p>';
	}
	
	static function callback()
	{
		$consumer_key 		= get_option('social_connect_twitter_consumer_key');
		$consumer_secret 	= get_option('social_connect_twitter_consumer_secret');
		$twitter_api 		= new EpiTwitter($consumer_key, $consumer_secret);
		
		$twitter_api->setToken($_GET['oauth_token']);
		$token = $twitter_api->getAccessToken();
		$twitter_api->setToken($token->oauth_token, $token->oauth_token_secret);
		
		$user 			= $twitter_api->get_accountVerify_credentials();
		$name 			= $user->name;
		$screen_name 	= $user->screen_name;
		$twitter_id 	= $user->id;
		$signature 		= SC_Utils::generate_signature($twitter_id);
		
		?>
		
		<html>
		<head>
		<script>
		function init() {
			window.opener.wp_social_connect({
				'action' : 'social_connect', 
				'social_connect_provider' : 'twitter', 
				'social_connect_signature' : '<?php echo $signature ?>',
				'social_connect_twitter_identity' : '<?php echo $twitter_id ?>',
				'social_connect_screen_name' : '<?php echo $screen_name ?>',
				'social_connect_name' : '<?php echo $name ?>'
			});
		
			window.close();
		}
		</script>
		</head>
		<body onload="init();">
		</body>
		</html>
		<?php

	}
	
	static function process_login()
	{
		$redirect_to 			= SC_Utils::redirect_to();
		$provider_identity 		= $_REQUEST[ 'social_connect_twitter_identity' ];
		$provided_signature 	= $_REQUEST[ 'social_connect_signature' ];
		
		SC_Utils::verify_signature( $provider_identity, $provided_signature, $redirect_to );
		
		$site_url 	= parse_url( site_url() );
		$names 		= explode(" ", $sc_name );
		
		return (object) array(
			'provider_identity' => $provider_identity,
			'email' 			=> 'tw_' . md5( $provider_identity ) . '@' . $site_url['host'],
			'first_name' 		=> $names[0],
			'last_name' 		=> $names[1],
			'profile_url'		=> '',
			'name' 				=> $_REQUEST[ 'social_connect_name' ],
			'user_login' 		=> $_REQUEST[ 'social_connect_screen_name' ]
		);
	}
	
}

SC_Gateway_Twitter::init();