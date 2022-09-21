<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete Options
$gmb_rating_options = array( 
    'gmb_rating_api_key', 
    'gmb_rating_place_id', 
    'gmb_rating_url', 
    'gmb_rating_average', 
    'gmb_ratings_total',
    'gmb_rating_api_error'
);

foreach ( $gmb_rating_options as $gmb_rating_setting_name ) {
    delete_option( $gmb_rating_setting_name );
}

// Delete WP CRON
wp_clear_scheduled_hook('gmb_rating_get_every_week');