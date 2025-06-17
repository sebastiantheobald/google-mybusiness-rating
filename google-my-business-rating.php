<?php
/*
Plugin Name: Automatic Update Google Business Profile Reviews
Description: Get Google Business Profile Rating as Badge
Version: 0.2.4
Author: Sebastian Theobald
Author URI: https://www.starts.design
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0
*/

defined( 'ABSPATH' ) or die( 'Are you ok?' );

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

    $rating = get_option('gmb_rating_average');
	$user_ratings_total = get_option('gmb_ratings_total');

    $rating = round($rating* 2, 0)/2;
    $gmb_profile_url = get_option('gmb_rating_url');

    // loop through stars
    $stars_html = "";
    for ($i = 1; $i <= 5; $i++) {
        if ( $rating >= $i ) {
            //$stars_html .= "<i class='fa fa-star yellow' aria-hidden='true'></i>";
            $stars_html .= "<svg class='yellow' width='18' height='18' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path fill='currentColor' d='M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z'/></svg>";

        } else if ( $rating == $i - 0.5 ) {
            //$stars_html .= "<i class='fa-solid fa-star-half-stroke yellow' aria-hidden='true'></i>";
            $stars_html .= "<svg class='yellow' width='18' height='18' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path fill='currentColor' d='M378.1 154.8L531.4 177.5C540.4 178.8 547.8 185.1 550.7 193.7C553.5 202.4 551.2 211.9 544.8 218.2L433.6 328.4L459.9 483.9C461.4 492.9 457.7 502.1 450.2 507.4C442.8 512.7 432.1 513.4 424.9 509.1L287.9 435.9L150.1 509.1C142.9 513.4 133.1 512.7 125.6 507.4C118.2 502.1 114.5 492.9 115.1 483.9L142.2 328.4L31.11 218.2C24.65 211.9 22.36 202.4 25.2 193.7C28.03 185.1 35.5 178.8 44.49 177.5L197.7 154.8L266.3 13.52C270.4 5.249 278.7 0 287.9 0C297.1 0 305.5 5.25 309.5 13.52L378.1 154.8zM287.1 384.7C291.9 384.7 295.7 385.6 299.2 387.5L404.4 443.7L384.2 324.1C382.9 316.4 385.5 308.5 391 303L476.9 217.9L358.6 200.5C350.7 199.3 343.9 194.3 340.5 187.2L287.1 79.09L287.1 384.7z'/></svg>";
        } else {
            //$stars_html .= "<i class='fa-regular fa-star' aria-hidden='true'></i>";
            $stars_html .= "<svg class='yellow' width='18' height='18' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'><!--! Font Awesome Pro 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path fill='currentColor' d='M287.9 0C297.1 0 305.5 5.25 309.5 13.52L378.1 154.8L531.4 177.5C540.4 178.8 547.8 185.1 550.7 193.7C553.5 202.4 551.2 211.9 544.8 218.2L433.6 328.4L459.9 483.9C461.4 492.9 457.7 502.1 450.2 507.4C442.8 512.7 432.1 513.4 424.9 509.1L287.9 435.9L150.1 509.1C142.9 513.4 133.1 512.7 125.6 507.4C118.2 502.1 114.5 492.9 115.1 483.9L142.2 328.4L31.11 218.2C24.65 211.9 22.36 202.4 25.2 193.7C28.03 185.1 35.5 178.8 44.49 177.5L197.7 154.8L266.3 13.52C270.4 5.249 278.7 0 287.9 0L287.9 0zM287.9 78.95L235.4 187.2C231.9 194.3 225.1 199.3 217.3 200.5L98.98 217.9L184.9 303C190.4 308.5 192.9 316.4 191.6 324.1L171.4 443.7L276.6 387.5C283.7 383.7 292.2 383.7 299.2 387.5L404.4 443.7L384.2 324.1C382.9 316.4 385.5 308.5 391 303L476.9 217.9L358.6 200.5C350.7 199.3 343.9 194.3 340.5 187.2L287.9 78.95z'/></svg>";
        }
    } 

    $html = 
		'
        <div id="gmb_rating_wrapper">
            <a aria-label="Google Bewertungen" target="_blank" class="gmb_rating_wrapper_link" href="' . $gmb_profile_url . '">
                <div class="gmb_rating_badge_wrapper">
                    <img alt="Google Logo" src="' . plugin_dir_url( __FILE__ ).'assets/images/googlelogo_color_272x92dp.png' . '" />

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
            width: auto;
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