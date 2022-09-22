<?php
/*
Plugin Name: Automatic Update Google Business Profile Reviews
Description: Get Google Business Profile Rating as Badge
Version: 0.1
Author: Sebastian Theobald
Author URI: https://www.starts.design
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0
*/

defined( 'ABSPATH' ) or die( 'Are you ok?' );

// Register Scripts
function gmb_rating_load_scripts() {
    wp_register_script('fontawesome', plugin_dir_url( __FILE__ ).'assets/fontawesome/all.js' );
}
add_action('wp_enqueue_scripts', 'gmb_rating_load_scripts');

// Add Plugin List Settings URL
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'gmb_rating_add_plugin_page_settings_link');
function gmb_rating_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=gmb_rating' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

// Add Option Field
register_activation_hook( __FILE__, 'gmb_rating_plugin_activation' );

function gmb_rating_plugin_activation() {
    $gmb_rating_options = array( 
        'gmb_rating_api_key', 
        'gmb_rating_place_id', 
        'gmb_rating_url', 
        'gmb_rating_average', 
        'gmb_ratings_total',
        'gmb_rating_api_error'
    );

    foreach ( $gmb_rating_options as $gmb_rating_setting_name ) {
        add_option( $gmb_rating_setting_name );
    }
}

// Register options page
function gmb_rating_add_settings_page() {
    add_options_page(
        'Google My Business Rating', 
        'Google My Business Rating', 
        'manage_options', 
        'gmb_rating', 
        'gmb_rating_page_callback'
    );
}
add_action( 'admin_menu', 'gmb_rating_add_settings_page' );

// Content of Settings Page
function gmb_rating_page_callback() {
    ?>
    <div id="wpbody" role="main">
        <div id="wpbody-content">
            <div class="wrap">
                <h1><?php echo get_admin_page_title() ?></h1>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'gmb_rating_settings' ); // settings group name
                        do_settings_sections( 'gmb_rating' ); // just a page slug
                        submit_button(); // "Save Changes" button
                    ?>
                </form>
                <div class="gmb-rating-copyright">
                    Entwickelt von <a href="https://www.starts.design/">STARTS Design GmbH</a>
                </div>
            </div>    
        </div>
    </div>
    <?php
}

// Register Option Fields
add_action( 'admin_init', 'gmb_rating_register_settings' );

function gmb_rating_register_settings() {

    // I created variables to make the things clearer
	$page_slug = 'gmb_rating';
	$option_group = 'gmb_rating_settings';

    // 1. create section
	add_settings_section(
		'gmb_rating_main_section', // section ID
		'General Settings', // title (optional)
		'', // callback function to display the section (optional)
		$page_slug
	);

    // 2. register fields
	register_setting( $option_group, 'gmb_rating_api_key' );
    register_setting( $option_group, 'gmb_rating_place_id' );
    register_setting( $option_group, 'gmb_rating_url' );

    // 3. add fields
	add_settings_field(
		'gmb_rating_api_key',
		'API Key',
		'gmb_rating_api_key_render',
		$page_slug,
		'gmb_rating_main_section',
		array(
			'label_for' => 'gmb_rating_api_key',
			'class' => 'api-key', // for <tr> element
			'name' => 'gmb_rating_api_key' // pass any custom parameters
		)
	);
    add_settings_field(
		'gmb_rating_place_id',
		'Place ID',
		'gmb_rating_place_id_render',
		$page_slug,
		'gmb_rating_main_section',
		array(
			'label_for' => 'gmb_rating_place_id',
			'class' => 'place-id', // for <tr> element
			'name' => 'gmb_rating_place_id' // pass any custom parameters
        )
	);
    add_settings_field(
		'gmb_rating_url',
		'URL to Google Reviewform',
		'gmb_rating_url_render',
		$page_slug,
		'gmb_rating_main_section',
		array(
			'label_for' => 'gmb_rating_url',
			'class' => 'rating-url', // for <tr> element
			'name' => 'gmb_rating_url' // pass any custom parameters
        )
	);
}

// custom callback function to print field HTML
function gmb_rating_api_key_render( $args ){
	printf(
		'<input type="text" id="%s" name="%s" value="%s" style="width:400px;" />',
		$args[ 'name' ],
		$args[ 'name' ],
		get_option( $args[ 'name' ] )
	);
}
function gmb_rating_place_id_render( $args ){
	printf(
		'<input type="text" id="%s" name="%s" value="%s" style="width:400px;" />',
		$args[ 'name' ],
		$args[ 'name' ],
		get_option( $args[ 'name' ] )
	);
}
function gmb_rating_url_render( $args ){
	printf(
		'<input type="url" id="%s" name="%s" value="%s" style="width:400px;" />',
		$args[ 'name' ],
		$args[ 'name' ],
		get_option( $args[ 'name' ] )
	);
}

/* Shortcode to display Google Rating */
add_shortcode('gmb_rating_badge', 'gmb_rating_show_rating');

// Trigger gmb_rating_get_rating function after updated API Key and Place ID
add_action('updated_option', 'gmb_rating_get_rating_trigger_function', 10, 3);

function gmb_rating_get_rating_trigger_function($option_name) {
    if( $option_name != "gmb_rating_api_key" AND $option_name != "gmb_rating_place_id") {
        return;
    }

    gmb_rating_get_rating();
}

function gmb_rating_get_rating() {

    if ( !get_option("gmb_rating_api_key") OR !get_option("gmb_rating_place_id") ) {
        return;
    }

    $api_key = get_option("gmb_rating_api_key");
    $place_id = get_option("gmb_rating_place_id");

    $gmb_rating_api_json_url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$place_id&fields=user_ratings_total,rating&key=$api_key";

    $gmb_rating_api_response = wp_remote_get(esc_url_raw($gmb_rating_api_json_url));
    $gmb_rating_api_response_code = wp_remote_retrieve_response_code( $gmb_rating_api_response );
    $gmb_rating_api_response_body = json_decode(wp_remote_retrieve_body($gmb_rating_api_response), true);
    $gmb_rating_api_status = $gmb_rating_api_response_body["status"];

    if ( $gmb_rating_api_response_code == 404 ) {
        update_option('gmb_rating_api_error', '404 - Server not found');
        return;
    }

    if ( isset($gmb_rating_api_response_body["error_message"]) ) {
        update_option("gmb_rating_api_error", $gmb_rating_api_response_body["error_message"]);
        return;
    }

    if ( isset($gmb_rating_api_status) ) {
        if ( $gmb_rating_api_status != "OK") {
            update_option("gmb_rating_api_error", $gmb_rating_api_status);
            return;
        }
    }

    if ( isset($gmb_rating_api_response_body["result"]["rating"]) AND isset($gmb_rating_api_response_body["result"]["user_ratings_total"] ) ) {
        $gmb_rating_rating = $gmb_rating_api_response_body['result']['rating'];
        $gmb_rating_user_ratings_total = $gmb_rating_api_response_body['result']['user_ratings_total'];

        if (isset($gmb_rating_rating) AND isset($gmb_rating_user_ratings_total)){
            update_option('gmb_rating_average', $gmb_rating_rating);
            update_option('gmb_ratings_total', $gmb_rating_user_ratings_total);

            update_option('gmb_rating_api_error', '');
        } else {
            update_option('gmb_rating_api_error', 'No ratings available from Google Places API');
        }
    }
}

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'gmb_rating_get_every_week' ) ) {
    wp_schedule_event( time(), 'weekly', 'gmb_rating_get_every_week' );
}

// Hook into that action that'll fire every week
add_action( 'gmb_rating_get_every_week', 'gmb_rating_get_rating' );

function gmb_rating_show_rating() {

    if ( !get_option('gmb_rating_average') OR !get_option('gmb_ratings_total')) {
        return "Keine Daten vorhanden. Bitte pflege die Plugin Einstellungen unter Einstellungen - Google My Business Rating";
    }

    wp_enqueue_script('fontawesome');

    $rating = get_option('gmb_rating_average');
	$user_ratings_total = get_option('gmb_ratings_total');

    $rating = round($rating* 2, 0)/2;
    $gmb_profile_url = get_option('gmb_rating_url');

    // loop through stars
    $stars_html = "";
    for ($i = 1; $i <= 5; $i++) {
        if ( $rating >= $i ) {
            $stars_html .= "<i class='fa fa-star yellow' aria-hidden='true'></i>";
        } else if ( $rating == $i - 0.5 ) {
            $stars_html .= "<i class='fa-solid fa-star-half-stroke yellow' aria-hidden='true'></i>";
        } else {
            $stars_html .= "<i class='fa-regular fa-star' aria-hidden='true'></i>";
        }
    } 

    $html = 
		'
        <div id="gmb_rating_wrapper">
            <a target="_blank" class="gmb_rating_wrapper_link" href="' . $gmb_profile_url . '">
                <div class="gmb_rating_badge_wrapper">
                    <img src="' . plugin_dir_url( __FILE__ ).'assets/images/googlelogo_color_272x92dp.png' . '" />

                    <div class="gmb_rating_average_rating">
                        <div class="gmb_rating_stars_wrapper">' .   
                            $stars_html
                        . '</div>
                    </div>
                    <div class="gmb_rating_additional_information">
                        <span>' . $rating . '/5</span> - 
                        <strong>' . $user_ratings_total . ' Bewertungen</strong>
                    </div>
                </div>
            </a>
        </div>';
	
	$html .= '
		<style>
        #gmb_rating_wrapper {
            display: inline;
        }
		.gmb_rating_wrapper_link {
			color: #000 !important;
            width: auto;
            text-decoration: none;
		}
		.gmb_rating_wrapper_link:hover {
			color: #000 !important;
		}
        .gmb_rating_badge_wrapper {
            height: 100px;
            padding: 10px;
			background: #FFF;
            /*border-radius: 5px;
            border: 1px solid #ececec;*/
            display: flex;
            flex-direction: column;
            align-items: center;
			justify-content: center;
            width: 200px;
        }
        .gmb_rating_badge_wrapper img {
			height: 28px !important;
        }
		.gmb_rating_badge_wrapper .gmb_rating_additional_information {
			font-size: 13px;
        }
        .gmb_rating_stars_wrapper {
            display: flex;
            position: relative;
			color: #dadada;
            color: #f8ce0b;
            margin: 5px 0;
        }
        .gmb_rating_stars_wrapper svg.yellow  {
            color: #f8ce0b;
        }
    </style>';
	
	return $html;
}

/**
 * Admin Notices and Warning
 */

function gmb_rating_admin_notice_warn() {

    $user = wp_get_current_user();
    if ( in_array( 'author', (array) $user->roles ) ) {
        return;
    }

    if( get_option("gmb_rating_api_key") AND get_option("gmb_rating_place_id") AND get_option("gmb_rating_url")) {
        return;
    }

    echo    '<div class="notice notice-warning is-dismissible">
                <p>Important: Your Google My Business Rating will not be displayed without adding all the required fields in <a href="options-general.php?page=gmb_rating">Google My Business Rating settings</a>.</p>
            </div>'; 
}
add_action( 'admin_notices', 'gmb_rating_admin_notice_warn' );

function gmb_rating_api_error_admin_notice() {

    $user = wp_get_current_user();
    if ( in_array( 'author', (array) $user->roles ) ) {
        return;
    }

    if( !get_option("gmb_rating_api_error") ) {
        return;
    }

    echo    '<div class="notice notice-error is-dismissible">
                <p>Google My Business Rating Plugin tries to get Data from Google Places API but failed. Error Code: ' . get_option("gmb_rating_api_error") . '</p>
            </div>'; 
}
add_action( 'admin_notices', 'gmb_rating_api_error_admin_notice' );