<?php

namespace Tarosky\Wpcli\Checksum;

use Exception;
use Tarosky\Wpcli\Checksum\Fetchers\UnfilteredPlugin;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI\WpOrgApi;

/**
 * Verifies plugin file integrity by comparing to published checksums.
 */
class Plugins_Command extends Command {

	/**
	 * Cached plugin data for all installed plugins.
	 *
	 * @var array|null
	 */
	private $plugins_data;

	/**
	 * Verifies plugin files against WordPress.org's checksums and return the result as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to verify.
	 *
	 * [--all]
	 * : If set, all plugins will be verified.
	 *
	 * [--strict]
	 * : If set, even "soft changes" like readme.txt changes will trigger
	 * checksum errors.
	 *
	 * [--version=<version>]
	 * : Verify checksums against a specific plugin version.
	 *
	 * [--insecure]
	 * : Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
	 *
	 * ## EXAMPLES
	 *
	 *     # Verify the checksums of all installed plugins
	 *     $ wp tarosky checksum plugins --all
	 *     [{"name":"akismet","verified":true},{"name":"classic-editor","verified":true},{"name":"hello-dolly","verified":true}]
	 *
	 *     # Verify the checksums of a single plugin, Akismet in this case
	 *     $ wp tarosky checksum plugins akismet
	 *     [{"name":"akismet","verified":true}]
	 */
	public function __invoke( $args, $assoc_args ) {
		$result = [];

		$fetcher     = new UnfilteredPlugin();
		$all         = (bool) Utils\get_flag_value( $assoc_args, 'all', false );
		$strict      = (bool) Utils\get_flag_value( $assoc_args, 'strict', false );
		$insecure    = (bool) Utils\get_flag_value( $assoc_args, 'insecure', false );
		$plugins     = $fetcher->get_many( $all ? self::get_all_plugin_names() : $args );
		$version_arg = isset( $assoc_args['version'] ) ? $assoc_args['version'] : '';

		if ( empty( $plugins ) && ! $all ) {
			WP_CLI::error( 'You need to specify either one or more plugin slugs to check or use the --all flag to check all plugins.' );
		}

		$succeeded = true;

		foreach ( $plugins as $plugin ) {
			$res       = $this->verify_plugin( $plugin, $version_arg, $insecure, $strict )->get();
			$result[]  = $res;
			$succeeded = $succeeded && $res['verified'];
		}

		echo json_encode( $result );
		if ( ! $succeeded ) {
			exit( 1 );
		}
	}

	private function verify_plugin( $plugin, $version_arg, $insecure, $strict ) {
		$result = new Result();
		$result->set_name( $plugin->name );

		$version = empty( $version_arg ) ? $this->get_plugin_version( $plugin->file ) : $version_arg;

		if ( false === $version ) {
			$result->set_reason( 'plugin_version_not_found' );
			return $result;
		}

		$wp_org_api = new WpOrgApi( [ 'insecure' => $insecure ] );

		try {
			$checksums = $wp_org_api->get_plugin_checksums( $plugin->name, $version );
		} catch ( Exception $exception ) {
			$result->set_message( $exception->getMessage() );
			$checksums = false;
		}

		if ( false === $checksums ) {
			$result->set_reason( 'plugin_checksum_not_found' );
			return $result;
		}

		$files = $this->get_plugin_files( $plugin->file );

		foreach ( $checksums as $file => $checksum_array ) {
			if ( ! in_array( $file, $files, true ) ) {
				$result->add_missing_file( $file );
			}
		}

		foreach ( $files as $file ) {
			if ( ! array_key_exists( $file, $checksums ) ) {
				$result->add_added_file( $file );
				continue;
			}

			if ( ! $strict && self::is_soft_change_file( $file ) ) {
				continue;
			}

			$matched = self::check_file_checksum( dirname( $plugin->file ) . '/' . $file, $checksums[ $file ] );
			if ( false === $matched ) {
				$result->add_mismatch_file( $file );
			} elseif ( null === $matched ) {
				$result->add_noalgorithm_file( $file );
			}
		}

		return $result;
	}

	/**
	 * Gets the currently installed version for a given plugin.
	 *
	 * @param string $path Relative path to plugin file to get the version for.
	 *
	 * @return string|false Installed version of the plugin, or false if not
	 *                      found.
	 */
	private function get_plugin_version( $path ) {
		if ( ! isset( $this->plugins_data ) ) {
			$this->plugins_data = get_plugins();
		}

		if ( ! array_key_exists( $path, $this->plugins_data ) ) {
			return false;
		}

		return $this->plugins_data[ $path ]['Version'];
	}

	/**
	 * Gets the names of all installed plugins.
	 *
	 * @return array<string> Names of all installed plugins.
	 */
	private static function get_all_plugin_names() {
		$names = array();
		foreach ( get_plugins() as $file => $details ) {
			$names[] = Utils\get_plugin_name( $file );
		}

		return $names;
	}

	/**
	 * Gets the list of files that are part of the given plugin.
	 *
	 * @param string $path Relative path to the main plugin file.
	 *
	 * @return array<string> Array of files with their relative paths.
	 */
	private function get_plugin_files( $path ) {
		$folder = dirname( self::get_absolute_path( $path ) );

		// Return single file plugins immediately, to avoid iterating over the
		// entire plugins folder.
		if ( WP_PLUGIN_DIR === $folder ) {
			return (array) $path;
		}

		return $this->get_files( trailingslashit( $folder ) );
	}

	/**
	 * Checks the integrity of a single plugin file by comparing it to the
	 * officially provided checksum.
	 *
	 * @param string $path      Relative path to the plugin file to check the
	 *                          integrity of.
	 * @param array  $checksums Array of provided checksums to compare against.
	 *
	 * @return bool|null
	 */
	private static function check_file_checksum( $path, $checksums ) {
		if ( array_key_exists( 'sha256', $checksums ) ) {
			$sha256 = hash_file( 'sha256', self::get_absolute_path( $path ) );
			return in_array( $sha256, (array) $checksums['sha256'], true );
		}

		if ( array_key_exists( 'md5', $checksums ) ) {
			$md5 = hash_file( 'md5', self::get_absolute_path( $path ) );
			return in_array( $md5, (array) $checksums['md5'], true );
		}

		return null;
	}

	/**
	 * Gets the absolute path to a relative plugin file.
	 *
	 * @param string $path Relative path to get the absolute path for.
	 *
	 * @return string
	 */
	private static function get_absolute_path( $path ) {
		return WP_PLUGIN_DIR . '/' . $path;
	}

	/**
	 * Returns a list of files that only trigger checksum errors in strict mode.
	 *
	 * @return array<string> Array of file names.
	 */
	private static function get_soft_change_files() {
		static $files = array(
			'readme.txt',
			'readme.md',
		);

		return $files;
	}

	/**
	 * Checks whether a given file will only trigger checksum errors in strict
	 * mode.
	 *
	 * @param string $file File to check.
	 *
	 * @return bool Whether the file only triggers checksum errors in strict
	 * mode.
	 */
	private static function is_soft_change_file( $file ) {
		return in_array( strtolower( $file ), self::get_soft_change_files(), true );
	}
}
