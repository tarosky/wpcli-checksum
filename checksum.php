<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

WP_CLI::add_command( 'tarosky', 'Tarosky\Wpcli\Checksum\Tarosky_Namespace' );
WP_CLI::add_command( 'tarosky checksum', 'Tarosky\Wpcli\Checksum\Checksum_Namespace' );
WP_CLI::add_command( 'tarosky checksum core', 'Tarosky\Wpcli\Checksum\Core_Command' );
WP_CLI::add_command( 'tarosky checksum plugins', 'Tarosky\Wpcli\Checksum\Plugins_Command' );
