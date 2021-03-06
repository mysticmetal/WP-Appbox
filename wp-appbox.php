<?php
/*
Plugin Name: WP-Appbox
Version: 3.4.8
Plugin URI: https://tchgdns.de/wp-appbox-app-badge-fuer-google-play-mac-app-store-windows-store-windows-phone-store-co/
Description: "WP-Appbox" ermöglicht es, via Shortcode schnell und einfach App-Details von Apps aus einer Reihe an App Stores in Artikeln oder Seiten anzuzeigen.
Author: Marcel Schmilgeit
Author URI: https://tchgdns.de
Text Domain: wp-appbox
Domain Path: /lang
*/


/*
Copyright (C)  2012-2016 Marcel Schmilgeit

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/


/* PHP-Fehlerausgabe deaktivieren */
error_reporting( 0 );
//

/**
* Ein paar Variablen
*/
$wpAppboxFirstShortcode = true;
load_plugin_textdomain( 'wp-appbox', false, basename( dirname( __FILE__ ) ) . '/lang/' );


/**
* Includierung benötigter Scripte und Dateien
*/
include_once( "inc/definitions.php" );
include_once( "inc/appboxdb.php" );
if ( is_admin() ) {
	include_once( "admin/admin.php" );
	include_once( "admin/user-profiles.php" );
	if ( $_GET['page'] == 'wp-appbox' ) {
		switch ( $_GET['tab'] ) {
			case 'storeurls':
			case 'advanced':
				include_once( "inc/getstoreurls.class.php" );
				break;
			case 'cache-list':
				include_once( "inc/getappinfo.class.php" );
				include_once( "inc/getstoreurls.class.php" );
				include_once( "inc/createoutput.class.php" );
				break;
		}
	}
}
if ( !is_admin() ) {
	include_once( "inc/getappinfo.class.php" );
	include_once( "inc/getstoreurls.class.php" );
	include_once( "inc/createattributs.class.php" );
	include_once( "inc/createoutput.class.php" );
}
if ( !defined('ABSPATH') ) {
	require_once('./wp-load.php');
}
require_once( ABSPATH . "wp-includes/pluggable.php" );


/**
* Prüfen ob der Nutzer Autor (min. User-Level 2) ist
*
* @since   2.0.0
* @change  3.2.0
*
* @return  boolean  true/false  TRUE when author
*/

function wpAppbox_isUserAuthor() {
	$userdata = get_userdata( get_current_user_id() );
	if ( ( isset($userdata) ) && ( intval( $userdata->user_level ) ) >= 2 ) {
		return( true );
	} else {
		return( false );
	}
}


/**
* Prüfen ob der Nutzer Admin (min. User-Level 9) ist
*
* @since   2.0.0
* @change  3.2.0
*
* @return  boolean  true/false  TRUE when admin
*/

function wpAppbox_isUserAdmin() {
	$userdata = get_userdata( get_current_user_id() );
	if ( intval( $userdata->user_level ) >= 9 ) {
		return( true );
	} else {
		return( false );
	}
}


/**
* Ausgabe der Fehlermeldungen
*
* @since   2.0.0
* @change  3.2.0
*
* @param   string  $output  Fehlermeldung [optional]
* @print   error message
*/

function wpAppbox_errorOutput( $output = "" ) {
	if ( get_option( "wpAppbox_eOutput" ) && wpAppbox_isUserAdmin() ) {
		print_r( "<pre>$output</pre>" );
	}
}


/**
* Prüfen ob "?wpappbox_nocache" angehangen
*
* @since   2.0.0
* @change  3.2.0
*
* @return  boolean  true/false  TRUE when $_GET[]
*/

function wpAppbox_isCacheInactive() {
	if ( wpAppbox_isUserAuthor() ) {
		if( isset( $_GET['wpappbox_nocache'] ) ){
			 return( true );
		} else {
			return( false );
		}
	}
}


/**
* Prüfen ob "?wpappbox_reload_cache" angehangen
*
* @since   2.0.0
* @change  3.2.0
*
* @return  boolean  true/false  TRUE when $_GET[]
*/

function wpAppbox_forceNewCache( $appID ) {
	if ( wpAppbox_isUserAuthor() ) {
		if ( ( isset( $_GET["wpappbox_reload_cache"] ) ) || ( isset( $_GET["action"] ) && $_GET["action"] === 'wpappbox_reload_cache' ) ) {
			if ( !isset( $_GET["wpappbox_cacheid"] ) ) {
				return( true );
			} elseif ( $_GET["wpappbox_cacheid"] === $appID ) {
				return( true );
			}
		}
	}
	return( false );
}


/**
* Einlesen des Templates
*
* @since   2.0.0
* @change  3.4.3
*
* @param   string   $styleName      Verwendeter Stil
* @param   boolean  $themeTemplate  Deprecated [optional]
* @return  string   $tpl            Ausgabe des Banners
*/

function wpAppbox_loadTemplate( $styleName, $themeTemplate = false ) {
	ob_start();
	if ( file_exists( get_template_directory() . "/wpappbox-$styleName.php" ) ) {
		include( get_template_directory()."/wpappbox-$styleName.php" );
	} else if ( file_exists( get_template_directory() . "/wpappbox/$styleName.php" ) ) {
		include( get_template_directory()."/wpappbox/$styleName.php" );
	} else {
		include( "tpl/$styleName.php" );
	}
	$tpl = ob_get_contents();
	ob_end_clean();
	return( $tpl );
}


/**
* Löscht den Seiten-Cache eines Cache-Plugins
*
* @since   3.2.0
*
* @param   string    $postID       ID des Posts
*/

function wpAppbox_clearCachePlugin( $postID = '') {
	global $post;
	$postID = $post->ID;
	if ( $postID != '' ) {
		$usedPlugin = get_option( 'wpappbox_cachePlugin' );
		switch ( $usedPlugin ) {
			case 'cachify':
				if ( has_action( 'cachify_remove_post_cache' ) ) {
				    do_action( 'cachify_remove_post_cache', $postID );
				}
				break;
			case 'w3-total-cache':
				if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
					w3tc_pgcache_flush_post( $postID );
				}
			case 'wp-super-cache':
				if ( function_exists( 'wp_cache_post_change' ) ) {
					$GLOBALS["super_cache_enabled"] = 1;
					wp_cache_post_change( $postID );
				}
				break;
			case 'wp-rocket':
				if ( function_exists( 'rocket_clean_post' ) ) {
					rocket_clean_post( $postID );
				}
				break;
			case 'wp-fastest-cache':
				if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
					$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $postID );
				}
				break;
			case 'zencache':
				if ( isset( $GLOBALS['zencache'] ) && method_exists( $GLOBALS['zencache'], 'auto_clear_post_cache' ) ) {
					$GLOBALS['zencache']->auto_clear_post_cache( $postID );
				}
				break;
			case 'cache-enabler':
				if ( has_action( 'ce_clear_post_cache' ) ) {
				    do_action( 'ce_clear_post_cache', $postID );
				}
				break;
		}
	}
}


/**
* Prüfen ob Versionsnummer älter oder neuer
*
* @since   3.1.6
* @change  3.2.1
*
* @param   string   $this_ver   Zu prüfende Versionsnummer
* @param   string   $com_ver    Versionsnummer in der Datenbank
* @return  boolean  true/false  TRUE when $this_ver neuer
*/

function wpAppbox_checkOlderVersion( $this_ver = '', $comp_ver = '' ) {
	if ( $this_ver == '' ) $this_ver = WPAPPBOX_PLUGIN_VERSION;
	if ( $comp_ver == '' ) $comp_ver = get_option( "wpAppbox_pluginVersion" );
	$this_ver = str_pad( str_replace( ".", "", $this_ver ), 5, '0', STR_PAD_RIGHT );
	$comp_ver = str_pad( str_replace( ".", "", $comp_ver ), 5, '0', STR_PAD_RIGHT );
	if ( $this_ver > $comp_ver ) {
		return( true );
	}
}


/**
* Appbox-Banner erstellen und ausgeben
*
* @since   2.0.0
* @change  3.2.17
*
* @param   string  $appboxAttributs  Attribute des Shortcodes
* @param   string  $content          Inhalte des Shortcodes [deprecated]
* @return  string  $output           Ausgabe des Banners
*/

function wpAppbox_createAppbox( $appboxAttributs, $content ) {
	if ( !is_admin() ) {
		global $wpAppboxFirstShortcode;
		$runtimeStart = microtime( true );
		$attr = new wpAppbox_CreateAttributs;
		$attr = $attr->devideAttributs( $appboxAttributs );
		$output = new wpAppbox_CreateOutput;
		$output = $output->theOutput( $attr );
		if ( $wpAppboxFirstShortcode ) {
			if ( !get_option('wpAppbox_disableDefer') ) {
				wpAppbox_RegisterStyle();
				wpAppbox_loadFonts();
			}
			$wpAppboxFirstShortcode = false;
		}
		$runtimeEnd = microtime( true );
		$runetimeResult = $runtimeEnd - $runtimeStart;
		wpAppbox_errorOutput( "function: wpAppbox_createAppbox() ---> Laufzeit: $runetimeResult Sekunden" );
		return( $output );
	}
}


/**
* Store-URLs automatisch erkennen und umwandeln
*
* @since   3.3.0
* @change  3.4.8
*
* @param   string  $appboxAttributs  Attribute des Shortcodes
*/

function wpAppbox_autoDetectLinks( $content ) {

	//Links zum App Store
	$pattern = array(	'/^(?:<p>)?http.?:\/\/.*?itunes.apple.com\/(?:.*?\/)?app\/(?:.*?\/)?id([0-9]{1,45}).*?(?:<\/p>)?$/m',
						'/^(?:<p>)?http.?:\/\/.*?itunes.apple.com\/WebObjects\/MZStore\.woa\/wa\/viewSoftware\?id=([0-9]{1,45}).*?(?:<\/p>)?$/m'
					);
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox appstore ' . $appID . ']' );
	}, $content );
	
	
	//Links zum Play Store
	$pattern = '/^(?:<p>)?http.?:\/\/play\.google\.com\/store\/apps\/details\?id=(.*?)(?:\&.*?)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox googleplay ' . $appID . ']' );
	}, $content );
	
	//Links zum Windows Store
	$pattern = '/^(?:<p>)?http.?:\/\/www\.microsoft\.com\/.*?\/store\/(?:apps|p)\/.*?\/(.*?)(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox windowsstore ' . $appID . ']' );
	}, $content );
	
	//Links zum Firefox Marketplace
	$pattern = '/^(?:<p>)?http.?:\/\/marketplace\.firefox\.com\/app\/(.*?)(?:\/)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox firefoxmarketplace ' . $appID . ']' );
	}, $content );
	
	//Links zu Amazon-Apps
	$pattern = '/^(?:<p>)?http.?:\/\/www\.amazon\.*(?:.*?)\/dp\/([A-Za-z0-9]*)(?:.*)(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox amazonapps ' . $appID . ']' );
	}, $content );
	
	//Links zu Firefox-Addons
	$pattern = '/^(?:<p>)?http.?:\/\/addons\.mozilla\.org\/.*?\/firefox\/addon\/(.*?)(?:\/)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox firefoxaddon ' . $appID . ']' );
	}, $content );
	
	//Links zum Chrome Web Store
	$pattern = '/^(?:<p>)?http.?:\/\/chrome\.google\.com\/webstore\/detail\/.*?\/(.*?)(?:\?.*?)?(?:\&.*?)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox chromewebstore ' . $appID . ']' );
	}, $content );
	
	//Links zu WordPress Plugins
	$pattern = '/^(?:<p>)?http.?:\/\/(?:www\.)?wordpress\.org\/plugins\/([A-Za-z0-9-]*)(?:.*)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox wordpress ' . $appID . ']' );
	}, $content );
	
	//Links zu Games von GOG.com
	$pattern = '/^(?:<p>)?http.?:\/\/(?:www\.)?gog\.com\/game\/([A-Za-z0-9-_]*)(?:.*)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox goodoldgames ' . $appID . ']' );
	}, $content );
	
	//Links zu Games von Steam
	$pattern = '/^(?:<p>)?http.?:\/\/store\.steampowered\.com\/app\/([A-Za-z0-9-_]*)(?:.*)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox steam ' . $appID . ']' );
	}, $content );
	
	//Links zu Opera Addons
	$pattern = '/^(?:<p>)?http.?:\/\/addons\.opera\.com\/(?:.*\/)?extensions\/details\/([A-Za-z0-9-_]*)(?:.*)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox operaaddons ' . $appID . ']' );
	}, $content );
	
	//Links zu den XDA Labs
	$pattern = '/^(?:<p>)?http.?:\/\/labs\.xda-developers\.com\/store\/app\/(.*?)(?:\&.*?)?(?:<\/p>)?$/m';
	$content = preg_replace_callback( $pattern, function ( $matches ) {
		$appID = Trim($matches[1]);
		return( '[appbox xda ' . $appID . ']' );
	}, $content );
	
	//Post-Content zurückgegen
	return( $content );
}
if ( get_option('wpAppbox_autoLinks') ) add_filter( 'the_content', 'wpAppbox_autoDetectLinks', 0 );


if ( is_admin() ) {
}

/**
* Benötigte Update-Funktionen durchführen
*
* @since   3.1.6
* @change  3.4.0
*/

if ( is_admin() ) wpAppbox_UpdateAction();

function wpAppbox_UpdateAction() {
	if ( wpAppbox_checkOlderVersion( '3.4.8' ) ) {
		delete_option( 'wpAppbox_showWatchIcon' );
	}
	/* Wenn vorherige Version älter als 3.4.0 */ 
	if ( wpAppbox_checkOlderVersion( '3.4.0' ) ) {
		if ( true == get_option('wpAppbox_affiliateAppleDev') ) {
			update_option( 'wpAppbox_affiliateAppleID', '', 'no' );
			update_option( 'wpAppbox_affiliateApple', false, 'no' );
		} elseif ( '' == get_option('wpAppbox_affiliateApple') ) {
			update_option( 'wpAppbox_affiliateAppleID', '', 'no' );
			update_option( 'wpAppbox_affiliateApple', false, 'no' );
		} else {
			$oldID = get_option('wpAppbox_affiliateApple');
			update_option( 'wpAppbox_affiliateAppleID', $oldID, 'no' );
			update_option( 'wpAppbox_affiliateApple', true, 'no' );
		}
		delete_option( 'wpAppbox_affiliateAppleDev' );
		if ( true == get_option('wpAppbox_affiliateAmazonDev') ) {
			update_option( 'wpAppbox_affiliateAmazonID', '', 'no' );
			update_option( 'wpAppbox_affiliateAmazon', false, 'no' );
		} elseif ( '' == get_option('wpAppbox_affiliateAmazon') ) {
			update_option( 'wpAppbox_affiliateAmazonID', '', 'no' );
			update_option( 'wpAppbox_affiliateAmazon', false, 'no' );
		} else {
			$oldID = get_option('wpAppbox_affiliateAmazon');
			update_option( 'wpAppbox_affiliateAmazonID', $oldID, 'no' );
			update_option( 'wpAppbox_affiliateAmazon', true, 'no' );
		}
		delete_option( 'wpAppbox_affiliateAmazonDev' );		
		if ( true == get_option('wpAppbox_affiliateMicrosoftDev') ) {
			update_option( 'wpAppbox_affiliateMicrosoftID', '', 'no' );
			update_option( 'wpAppbox_affiliateMicrosoftProgram', '', 'no' );
			update_option( 'wpAppbox_affiliateMicrosoft', false, 'no' );
		} elseif ( ( '' == get_option('wpAppbox_affiliateMicrosoftProgram') ) || ( '' == get_option('wpAppbox_affiliateMicrosoft') ) ) {
			update_option( 'wpAppbox_affiliateMicrosoftID', '', 'no' );
			update_option( 'wpAppbox_affiliateMicrosoftProgram', '', 'no' );
			update_option( 'wpAppbox_affiliateMicrosoft', false, 'no' );
		} else {
			$oldID = get_option('wpAppbox_affiliateMicrosoft');
			update_option( 'wpAppbox_affiliateMicrosoftID', $oldID, 'no' );
			update_option( 'wpAppbox_affiliateMicrosoft', true, 'no' );
		}
		delete_option( 'wpAppbox_affiliateMicrosoftDev' );
	}
	/* Wenn vorherige Version älter als 3.3.0 */ 
	if ( wpAppbox_checkOlderVersion( '3.3.0' ) ) {
		wpAppbox_setOptions();
		global $wpdb;
		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'wpAppbox_%'" );
	}
	/* Wenn vorherige Version älter als 3.2.12 */ 
	if ( wpAppbox_checkOlderVersion( '3.2.12' ) ) {
		wpAppbox_setOptions();
	}
	/* Wenn vorherige Version älter als 3.2.3 */ 
	if ( wpAppbox_checkOlderVersion( '3.2.3' ) ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'wpAppbox_defaultStyle_%'" );
	}
	/* Wenn vorherige Version älter als 3.2.0 */ 
	if ( wpAppbox_checkOlderVersion( '3.2.0' ) ) {
		wpAppbox_setOptions();
		wpAppbox_createTable();
		wpAppbox_transformTransients();
	}
	/* Wenn vorherige Version älter als 3.1.8 */ 
	if ( wpAppbox_checkOlderVersion( '3.1.8' ) ) {
		wpAppbox_updateOptions();
	}
	/* Grundsätzlich nach Update zu prüfen */ 
	if ( get_option('wpAppbox_dbVersion') != WPAPPBOX_DB_VERSION ) {
		wpAppbox_createTable();
	}
	/* Neue Versionsnummer in die Datenbank schreiben */ 
	update_option( "wpAppbox_pluginVersion", WPAPPBOX_PLUGIN_VERSION );
}


/**
* Neue Optionen in wp_options einfügen
*
* @since   3.1.6
* @change  3.2.3
*/

function wpAppbox_setOptions() {
	global $wpAppbox_optionsDefault, $wpAppbox_storeNames;
	foreach ( $wpAppbox_optionsDefault as $key => $value ) {
		$key = 'wpAppbox_'.$key;
		if ( get_option( $key ) === false ) {
			update_option( $key, $value, 'no' );
		}
	}
	foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
		$key_buttonAppbox = "wpAppbox_buttonAppbox_$storeID";
		$key_buttonWYSIWYG = "wpAppbox_buttonWYSIWYG_$storeID";
		$key_buttonHTML = "wpAppbox_buttonHTML_$storeID";
		$key_buttonHidden = "wpAppbox_buttonHidden_$storeID";
		$key_storeURL = "wpAppbox_storeURL_$storeID";
		$key_storeURL_URL = "wpAppbox_storeURL_URL_$storeID";
		if ( get_option( $key_buttonWYSIWYG ) === false ) {
			update_option( $key_buttonWYSIWYG, true, 'no' );
		}
		if ( get_option( $key_buttonHTML ) === false ) {
			update_option( $key_buttonHTML, true, 'no' );
		}
		if ( get_option( $key_storeURL ) === false ) {
			update_option( $key_storeURL, intval( "1" ), 'no' );
		}
		if ( get_option( $key_storeURL_URL ) === false ) {
			update_option( $key_storeURL_URL, "", 'no' );
		}
	}
	update_option( "wpAppbox_pluginVersion", WPAPPBOX_PLUGIN_VERSION );
}


/**
* Prüft ob die Daten für die Amazon-API korrekt sind
*
* @since   3.4.0
* @change  n/a
*
* @return  boolean  true/false  TRUE when valid
*/

function wpAppbox_checkAmazonAPI() {
	if ( true == get_option( 'wpAppbox_amaAPIuse' ) ) {
		$amaRegion = get_option( 'wpAppbox_amaAPIregion' );
		$amaSecretKey = base64_decode( get_option( 'wpAppbox_amaAPIsecretKey' ) );
		$params["AWSAccessKeyId"]   = get_option( 'wpAppbox_amaAPIpublicKey' );
		$params["AssociateTag"]     = get_option( 'wpAppbox_affiliateAmazonID' );
		$params["Service"]          = 'AWSECommerceService';
		$params["Operation"]     	= 'ItemLookup';
		$params["Timestamp"]        = gmdate( "Y-m-d\TH:i:s\Z" );
		$params["Version"]          = "2013-08-01";
		ksort( $params );
		$canonicalizedQuery = array();
		foreach ( $params as $param => $value ) {
		    $param = str_replace( "%7E", "~", rawurlencode( $param ) );
		    $value = str_replace( "%7E", "~", rawurlencode( $value ) );
		    $canonicalizedQuery[] = $param . "=" . $value;
		}
		$canonicalizedQuery = implode( "&", $canonicalizedQuery );
		$stringToSign = "GET\necs.amazonaws.$amaRegion\n/onca/xml\n" . $canonicalizedQuery;
		$amaSignature = base64_encode( hash_hmac( "sha256", $stringToSign, $amaSecretKey, true ) );
		$amaSignature = str_replace( "%7E", "~", rawurlencode( $amaSignature ) );
		$amaRequest = "http://ecs.amazonaws.$amaRegion/onca/xml?" . $canonicalizedQuery . "&Signature=" . $amaSignature;
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $amaRequest );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 3 );
		$amaResult = curl_exec( $ch );
		curl_close( $ch );
		if ( $amaResult != str_replace( 'InvalidClientTokenId', '', $amaResult ) ) {
			return( false );
		}
		if ( $amaResult != str_replace( 'SignatureDoesNotMatch', '', $amaResult ) ) {
			return( false );
		}
		return( true );
	}
	return( false );
}



/**
* Alte Optionen übernehmen [deprecated] (für ältere Versionen ==> bis 3.1.6)
*
* @since   3.1.6
* @change  3.2.1
* @remove  >3.1.x
*/

function wpAppbox_updateOptions() {
	if ( get_option("wpAppbox") !== false ) {
		$oldSettings = get_option( "wpAppbox" );
		if ( !empty( $oldSettings ) ) {
			global $wpAppbox_optionsDefault, $wpAppbox_storeNames;
			if ( $oldSettings['wpAppbox_datacachetime'] != '' ) update_option( 'wpAppbox_cacheTime', $oldSettings['wpAppbox_datacachetime'], 'no' );
			if ( $oldSettings['wpAppbox_nofollow'] != ('' || false) ) update_option( 'wpAppbox_nofollow', $oldSettings['wpAppbox_nofollow'], 'no' );
			if ( $oldSettings['wpAppbox_blank'] != ('' || false) ) update_option( 'wpAppbox_targetBlank', $oldSettings['wpAppbox_blank'], 'no' );
			if ( $oldSettings['wpAppbox_showrating'] != ('' || false) ) update_option( 'wpAppbox_showRating', $oldSettings['wpAppbox_showrating'], 'no' );
			if ( $oldSettings['wpAppbox_colorful'] != ('' || false) ) update_option( 'wpAppbox_colorfulIcons', $oldSettings['wpAppbox_colorful'], 'no' );
			if ( $oldSettings['wpAppbox_show_reload_link'] != ('' || false) ) update_option( 'wpAppbox_showReload', $oldSettings['wpAppbox_show_reload_link'], 'no' );
			if ( $oldSettings['wpAppbox_downloadtext'] != '' ) update_option( 'wpAppbox_downloadCaption', $oldSettings['wpAppbox_downloadtext'], 'no' );
			if ( $oldSettings['wpAppbox_useownsheet'] != ('' || false) ) update_option( 'wpAppbox_disableCSS', $oldSettings['wpAppbox_useownsheet'], 'no' );
			if ( $oldSettings['wpAppbox_avoid_loadfonts'] != ('' || false) ) update_option( 'wpAppbox_disableFonts', $oldSettings['wpAppbox_avoid_loadfonts'], 'no' );
			if ( $oldSettings['wpAppbox_error_onlyforauthor'] != ('' || false) ) update_option( 'wpAppbox_eOnlyAuthors', $oldSettings['wpAppbox_error_onlyforauthor'], 'no' );
			if ( $oldSettings['wpAppbox_error_erroroutput'] != ('' || false) ) update_option( 'wpAppbox_eOutput', $oldSettings['wpAppbox_error_erroroutput'], 'no' );
			if ( $oldSettings['wpAppbox_itunes_secureimage'] != ('' || false) ) update_option( 'wpAppbox_eImageApple', $oldSettings['wpAppbox_itunes_secureimage'], 'no' );
			if ( $oldSettings['wpAppbox_curl_timeout'] != '' ) update_option( 'wpAppbox_curlTimeout', $oldSettings['wpAppbox_curl_timeout'], 'no' );
			if ( $oldSettings['wpAppbox_user_affiliateids'] != ('' || false) ) update_option( 'wpAppbox_userAffiliate', $oldSettings['wpAppbox_user_affiliateids'], 'no' );
			if ( $oldSettings['wpAppbox_affid'] != '' ) update_option( 'wpAppbox_affiliateApple', $oldSettings['wpAppbox_affid'], 'no' );
			if ( $oldSettings['wpAppbox_affid_sponsored'] != ('' || false) ) update_option( 'wpAppbox_affiliateAppleDev', $oldSettings['wpAppbox_affid_sponsored'], 'no' );
			if ( $oldSettings['wpAppbox_affid_amazonpartnernet'] != '' ) update_option( 'wpAppbox_affiliateAmazon', $oldSettings['wpAppbox_affid_amazonpartnernet'], 'no' );
			if ( $oldSettings['wpAppbox_affid_amazonpartnernet_sponsored'] != ('' || false) ) update_option( 'wpAppbox_affiliateAmazonDev', $oldSettings['wpAppbox_affid_amazonpartnernet_sponsored'], 'no' );
			if ( $oldSettings['wpAppbox_view_default'] != '' ) update_option( 'wpAppbox_defaultStyle', $oldSettings['wpAppbox_view_default'], 'no' );
			if ( $oldSettings['wpAppbox_button_default'] != '' ) update_option( 'wpAppbox_defaultButton', $oldSettings['wpAppbox_button_default'], 'no' );
			foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
				$key_defaultStyle = "wpAppbox_defaultStyle_$storeID";
				$key_buttonAppbox = "wpAppbox_buttonAppbox_$storeID";
				$key_buttonWYSIWYG = "wpAppbox_buttonWYSIWYG_$storeID";
				$key_buttonHTML = "wpAppbox_buttonHTML_$storeID";
				$key_buttonHidden = "wpAppbox_buttonHidden_$storeID";
				$key_storeURL = "wpAppbox_storeURL_$storeID";
				$key_storeURL_URL = "wpAppbox_storeURL_URL_$storeID";
				if ( $oldSettings['wpAppbox_view_'.$storeID] != '' ) update_option( $key_defaultStyle, intval( $oldSettings["wpAppbox_view_$storeID"] ), 'no' );
				if ( $oldSettings['wpAppbox_button_appbox_'.$storeID] != ('' || false) ) update_option( $key_buttonAppbox, $oldSettings["wpAppbox_button_appbox_$storeID"], 'no' );
				if ( $oldSettings['wpAppbox_button_alone_'.$storeID] != ('' || false) ) update_option( $key_buttonWYSIWYG, $oldSettings["wpAppbox_button_alone_$storeID"], 'no' );
				if ( $oldSettings['wpAppbox_button_html_'.$storeID] != ('' || false) ) update_option( $key_buttonHTML, $oldSettings["wpAppbox_button_html_$storeID"], 'no' );
				if ( $oldSettings['wpAppbox_button_hidden_'.$storeID] != ('' || false) ) update_option( $key_buttonHidden, $oldSettings["wpAppbox_button_hidden_$storeID"], 'no' );
				if ( $oldSettings['wpAppbox_storeurl_'.$storeID] != ('' || false) ) update_option( $key_storeURL, intval( $oldSettings["wpAppbox_storeurl_$storeID"] ), 'no' );
				if ( $oldSettings['wpAppbox_storeurl_url'.$storeID] != ('' || false) ) update_option( $key_storeURL_URL, $oldSettings["wpAppbox_storeurl_url$storeID"], 'no' );
			}
			update_option('wpAppbox_pluginVersion', WPAPPBOX_PLUGIN_VERSION, 'no');
			delete_option('wpAppbox'); //Für ältere Versionen ==> bis 3.1.6
		}
	}
}


/**
* WP-Appbox-Button zum WYSIWYG-Editor hinzufügen
*
* @since   3.2.10
*
* @return  void
*/

function wpAppbox_addCombinedButton() {
	global $wpAppbox_storeNames;
	$defaultOption = get_option( 'wpAppbox_defaultButton' );
	$combinedButton = array();
	$combinedButtonNames = array();
	$combinedButtonIDs = array();
	foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
		if ( ( '1' == $defaultOption ) || get_option( 'wpAppbox_buttonAppbox_' . $storeID ) ) {
			$combinedButtonNames[] = $storeName;
			$combinedButtonIDs[] = $storeID;
		}
	}
	if ( !empty( $combinedButtonNames) && !empty( $combinedButtonIDs ) ) {
		$combinedButton['names'] = $combinedButtonNames;
		$combinedButton['ids'] = $combinedButtonIDs;
		?>
		<script type="text/javascript">
			var wpappbox_combined_button = <?php echo( json_encode( $combinedButton ) ); ?>;
		</script>
		<?php 
	}
}


/**
* Buttons zum WYSIWYG-Editor hinzufügen
*
* @since   2.0.0
* @change  3.2.0
*
* @param   array  $buttons  Buttons [WordPress]
* @return  array  $buttons  Buttons [WordPress]
*/

function wpAppbox_addButtonsWYSIWYG( $buttons ) {
	global $wpAppbox_storeNames;
	$defaultOption = get_option( 'wpAppbox_defaultButton' );
	/**
	* WP-Appbox-Button
	*/
	if ( ( $defaultOption == '1' ) || ( $defaultOption == '3' ) ) {
		$combinedButton = array();
		$combinedButtonNames = array();
		$combinedButtonIDs = array();
		foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
			if ( ( '1' == $defaultOption ) || get_option( 'wpAppbox_buttonAppbox_' . $storeID ) ) {
				$combinedButtonNames[] = $storeName;
				$combinedButtonIDs[] = $storeID;
			}
		}
		if ( count( $combinedButtonNames ) == 1 && count( $combinedButtonIDs ) == 1 ) {
			$forceSingle = $combinedButtonIDs[0];
		}
		else if ( !empty( $combinedButtonNames) && !empty( $combinedButtonIDs ) ) {
			$combinedButton['names'] = $combinedButtonNames;
			$combinedButton['ids'] = $combinedButtonIDs;
			?>
			<script type="text/javascript">
				var wpappbox_combined_button = <?php echo( json_encode( $combinedButton ) ); ?>;
			</script>
			<?php 
		}
	}
	/**
	* Einfache Buttons
	*/
	if ( ( $defaultOption == '0' ) || ( $defaultOption == '3' ) ) {
		if ( '0' == $defaultOption || $forceSingle == 'amazonapps' || get_option('wpAppbox_buttonWYSIWYG_amazonapps') ) {
			array_push( $buttons, 'separator', 'wpAppbox_AmazonAppsButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'appstore' || get_option('wpAppbox_buttonWYSIWYG_appstore') ) {
			array_push( $buttons, 'separator', 'wpAppbox_AppStoreButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'chromewebstore' || get_option('wpAppbox_buttonWYSIWYG_chromewebstore') ) {
			array_push( $buttons, 'separator', 'wpAppbox_ChromeWebStoreButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'firefoxaddon' || get_option('wpAppbox_buttonWYSIWYG_firefoxaddon') ) {
			array_push( $buttons, 'separator', 'wpAppbox_FirefoxAddonButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'firefoxmarketplace' || get_option('wpAppbox_buttonWYSIWYG_firefoxmarketplace') ) {
			array_push( $buttons, 'separator', 'wpAppbox_FirefoxMarketplaceButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'googleplay' || get_option('wpAppbox_buttonWYSIWYG_googleplay') ) {
			array_push( $buttons, 'separator', 'wpAppbox_GooglePlayButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'operaaddons' || get_option('wpAppbox_buttonWYSIWYG_operaaddons') ) {
			array_push( $buttons, 'separator', 'wpAppbox_OperaAddonsButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'steam' || get_option('wpAppbox_buttonWYSIWYG_steam') ) {
			array_push( $buttons, 'separator', 'wpAppbox_SteamButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'windowsstore' || get_option('wpAppbox_buttonWYSIWYG_windowsstore') ) {
			array_push( $buttons, 'separator', 'wpAppbox_WindowsStoreButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'wordpress' || get_option('wpAppbox_buttonWYSIWYG_wordpress') ) {
			array_push( $buttons, 'separator', 'wpAppbox_WordPressButton' );
		}
		if ( '0' == $defaultOption || $forceSingle == 'xda' || get_option('wpAppbox_buttonWYSIWYG_xda') ) {
			array_push( $buttons, 'separator', 'wpAppbox_XDAButton' );
		}
	}
	if ( ( $option == '1' || '3' ) ) {
		array_push( $buttons, 'separator', 'wpAppbox_AppboxButton' );
	}
	return( $buttons );
}


/**
* Buttons zum HTML-Editor hinzufügen
*
* @since   2.0.0
* @change  3.2.0
*
* @echo    string   Ausgabe des Scripts innerhalb TinyMCE
*/

function wpAppbox_addButtonsHTML() {
	if ( is_admin() ) {
		global $wpAppbox_storeNames;
		$option = get_option('wpAppbox_defaultButton');
		if ( $option != '2' ) {
			if ( wp_script_is( 'quicktags' ) ) {
				echo( "<script type=\"text/javascript\">" );
				foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
					if ( get_option('wpAppbox_buttonHTML_'.$storeID) || $option == '0' ) echo( "QTags.addButton('htmlx_$storeID', 'Appbox: $storeID', '[appbox $storeID appid]', '', '', '$storeName');" );
				}
				echo( "</script>" );
			}
		}
	}
}


/**
* Registrierung des Plugins
*
* @since   2.0.0
* @change  3.2.10
*
* @param   array  $plugin_array     Plugin-Array [WordPress]
* @return  array  $plugin_array     Plugin-Array [WordPress]
*/

function wpAppbox_register( $plugin_array ) {
	global $wpAppbox_storeNames;
	$option = get_option('wpAppbox_defaultButton');
	if ( '2' != $option ) {
		foreach ( $wpAppbox_storeNames as $storeID => $storeName ) {
			if ( get_option("wpAppbox_buttonAppbox_$storeID") ) $iscombined = true;
		}
		$plugin_array['wpAppbox_CombinedButton'] = plugins_url( "buttons/buttons.min.js", __FILE__ );
		$plugin_array["wpAppboxSingle"] = plugins_url( "buttons/buttons.min.js", __FILE__ );
		return( $plugin_array );
	}
}


/**
* "Einstellungen"-Link zur Plugin-Liste hinzufügen
*
* @since   2.0.0
* @change  3.2.0
*
* @param   array   $links  Array der eingetragenen Links [WordPress]
* @param   string  $file   Aufgerufene Datei [WordPress]
* @return  array   $links  Rückgabe der überarbeiteten Links [WordPress]
*/

function wpAppbox_addSettings( $links, $file ) {
	static $this_plugin;
	if ( !$this_plugin ) $this_plugin = plugin_basename( __FILE__ );
	if ( $file == $this_plugin ) {
		$settings_link = '<a href="options-general.php?page=wp-appbox">' . esc_html__('Settings', 'wp-appbox') . '</a>';
		$links = array_merge( array( $settings_link ), $links );
	}
	return( $links );
}


/**
* Weitere Links zur Plugin-Beschreibung in der Liste hinzufügen
*
* @since   2.0.0
* @change  3.2.0
*
* @param   array   $links  Array der eingetragenen Links [WordPress]
* @param   string  $file   Aufgerufene Datei [WordPress]
* @return  array   $links  Rückgabe der überarbeiteten Links [WordPress]
*/

function wpAppbox_addLinks( $links, $file ) {
	static $this_plugin;
	if ( !$this_plugin ) {
		$this_plugin = plugin_basename( __FILE__ );
	}
	if ( $file == $this_plugin ) {
		$links = array();
		$links[] = 'Version '.WPAPPBOX_PLUGIN_VERSION;
		$links[] = '<a target="_blank" href="https://twitter.com/Marcelismus">' . esc_html__('Follow me on Twitter', 'wp-appbox') . '</a>';
		$links[] = '<a target="_blank" href="' . ( ( get_locale() == 'de_DE' ) ? 'https://tchgdns.de/wp-appbox-app-badge-fuer-google-play-mac-app-store-windows-store-windows-phone-store-co/' : 'https://translate.google.de/translate?hl=de&sl=de&tl=en&u=https%3A%2F%2Ftchgdns.de%2Fwp-appbox-app-badge-fuer-google-play-mac-app-store-windows-store-windows-phone-store-co%2F' ) . '">' . esc_html__('Plugin page', 'wp-appbox') . '</a>';
		$links[] = '<a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-appbox">' . esc_html__('Rate the plugin', 'wp-appbox') . '</a>';
		$links[] = '<a target="_blank" href="http://www.amazon.de/gp/registry/wishlist/1FC2DA2J8SZW7">' . esc_html__('My Amazon Wishlist', 'wp-appbox') . '</a>';
		$links[] = '<a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SH9AAS276RAS6">' . esc_html__('PayPal-Donation', 'wp-appbox') . '</a>';
	}
	return( $links );
}


/**
* Ausgabe von Fehlermeldungen
*
* @since   2.0.0
* @change  3.2.0
*
* @param   string  $message  Fehlermeldung
*/

function br_trigger_error( $message ) {
    if ( isset( $_GET['action'] ) && $_GET['action'] == 'error_scrape' ) {
        echo( "<strong>$message</strong>" );
        exit;
    } else {
    	trigger_error( $message, E_USER_ERROR );
    }
}


/**
* Aktivierung des Plugins
*
* @since   2.0.0
* @change  3.2.7
*/

function wpAppbox_activatePlugin( $network_wide ) {
	if ( version_compare( phpversion(), '5.3' ) == -1 ) br_trigger_error( esc_html__('To use this plugin requires at least PHP version 5.3 is required.', 'wp-appbox') );
	if ( !function_exists('curl_init') ) br_trigger_error( esc_html__('"cURL" is disabled on this server, but is required. Please enable this feature (or contact your hoster).', 'wp-appbox') ); 
	if ( !function_exists('curl_exec') ) br_trigger_error( esc_html__('"curl_exec" is disabled on this server, but is required. Please enable this feature (or contact your hoster).', 'wp-appbox') ); 
	if ( !function_exists('json_decode') ) br_trigger_error( esc_html__('"json_decode" is disabled on this server, but is required. Please enable this feature (or contact your hoster).', 'wp-appbox') );
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
		global $wpdb;
		$current_blog = $wpdb->blogid;
		$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog );
			wpAppbox_activateActions();
		}
		switch_to_blog( $current_blog );
	} else {
		wpAppbox_activateActions();
	}
}


/**
* Aktivierung-Actions des Plugins
*
* @since  3.2.7
*/

function wpAppbox_activateActions() {
	wpAppbox_setOptions(); /* Standard-Einstellungen in wp_options schreiben */
	wpAppbox_createTable(); /* Tabelle für "WP-Appbox" erstellen */
}


/**
* Deinstallation des Plugins
*
* @since   2.0.0
* @change  3.2.7
*/

function wpAppbox_uninstallPlugin() {
    if ( function_exists( 'is_multisite' ) && is_multisite() ) {
        global $wpdb;
        $current_blog = $wpdb->blogid;
        $blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blogs as $blog ) {
        	switch_to_blog( $blog );
        	wpAppbox_uninstallActions();
        }
        switch_to_blog( $current_blog );
    } else {
    	wpAppbox_uninstallActions();
    }
}


/**
* Deinstallation-Actions des Plugins
*
* @since  3.2.2
*/

function wpAppbox_uninstallActions() {
	global $wpdb;
	$wpdb->query( "DELETE FROM " . $wpdb->prefix . "options WHERE option_name LIKE 'wpAppbox_%';" );
	delete_option( "wpAppbox" ); //Für ältere Versionen ==> bis 3.1.6
	wpAppbox_deleteTable();
}


/**
* Aktivierung für neue Multisite-Blogs nach Plugin-Aktivierung
*
* @since  3.2.7
*/

function wpAppbox_activateBlogMultisite( $blogID ) {
    global $wpdb;
    if ( is_plugin_active_for_network('wp-appbox/wp-appbox.php') ) {
		switch_to_blog( $blogID );
		my_plugin_activate();
		restore_current_blog();
    }
}
add_action( 'wpmu_new_blog', 'wpAppbox_activateBlogMultisite' );


/**
* Stylesheet des Plugins registrieren
*
* @since   2.0.0
* @change  3.2.0
*/

function wpAppbox_RegisterStyle() {
	if ( get_option('wpAppbox_disableCSS') == false ) {
		wp_register_style( 'wpappbox', plugins_url( 'css/styles.min.css', __FILE__ ), array(), WPAPPBOX_PLUGIN_VERSION, 'screen' );
		wp_enqueue_style( 'wpappbox' );
	}
}


/**
* Google Fonts für das Plugin registrieren
*
* @since   2.0.0
* @change  3.2.0
*/

function wpAppbox_loadFonts() {
	if ( get_option('wpAppbox_disableFonts') == false ) {
		wp_register_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:400,600' );
		wp_enqueue_style( 'open-sans' );
	}
}


/* Diverse Filter, Aktionen und Hooks registrieren */
add_filter( 'mce_external_plugins', "wpAppbox_register" );
add_filter( 'mce_buttons', 'wpAppbox_addButtonsWYSIWYG', 0 );
add_filter( 'plugin_action_links', 'wpAppbox_addSettings', 10, 2 );
add_filter( 'plugin_row_meta', 'wpAppbox_addLinks', 10, 2 );
add_action( 'plugins_loaded', 'wpAppbox_UpdateAction' );
add_action( 'admin_menu', 'wpAppbox_pageInit' );
add_action( 'admin_print_footer_scripts', 'wpAppbox_addButtonsHTML' );
register_activation_hook( __FILE__, 'wpAppbox_activatePlugin' );
register_uninstall_hook( __FILE__, 'wpAppbox_uninstallPlugin' );


/* Stylesheet und Font auf den normalen Weg laden */
if ( get_option('wpAppbox_disableDefer') ) {
	add_action( 'wp_enqueue_scripts', 'wpAppbox_RegisterStyle' );
	add_action( 'wp_print_styles', 'wpAppbox_loadFonts' );
}


/* DER Shortcode */ 
add_shortcode( 'appbox', 'wpAppbox_createAppbox' );


?>