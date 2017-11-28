<?php

use \WP_CLI\Utils;

/**
 * Verifies plugin file integrity by comparing to published checksums.
 *
 * @package wp-cli
 */
class Checksum_Plugin_Command extends Checksum_Base_Command {

	/**
	 * URL template that points to the API endpoint to use.
	 *
	 * @var string
	 */
	private $url_template = 'https://downloads.wordpress.org/plugin-checksums/{slug}/{version}.json';

	/**
	 * Cached plugin data for all installed plugins.
	 *
	 * @var array|null
	 */
	private $plugins_data;

	/**
	 * Verify plugin files against WordPress.org's checksums.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to verify.
	 *
	 * [--all]
	 * : If set, all plugins will be verified.
	 */
	public function __invoke( $args, $assoc_args ) {

		$fetcher = new \WP_CLI\Fetchers\Plugin();
		$plugins = $fetcher->get_many( $args );
		$all     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( empty( $plugins ) && ! $all ) {
			WP_CLI::error( 'You need to specify either one or more plugin slugs to check or use the --all flag to check all plugins.' );
		}

		$errors = array();

		foreach ( $plugins as $plugin ) {
			$version = $this->get_plugin_version( $plugin->file );

			if ( false === $version ) {
				continue;
			}

			$checksums = $this->get_plugin_checksums( $plugin->name, $version );

			$files = $this->get_plugin_files( $plugin->file );

			foreach ( $files as $file ) {
				$result = $this->check_file( $file, $checksums );
				if ( true !== $result ) {
					$errors[ $plugin ][ $file ] = $result;
				}
			}
		}

		if ( empty( $errors ) ) {
			WP_CLI::success(
				count( $plugins ) > 1
					? 'Plugins verify against checksums.'
					: 'Plugin verifies against checksums.'
			);
		} else {
			WP_CLI::error(
				count( $plugins ) > 1
					? 'One or more plugins don\'t verify against checksums.'
					: 'Plugin doesn\'t verify against checksums.'
			);
		}
	}

	/**
	 * Get the currently installed version for a given plugin.
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
	 * Gets the checksums for the given version of plugin.
	 *
	 * @param string $version Version string to query.
	 * @param string $plugin  plugin string to query.
	 *
	 * @return bool|array False on failure. An array of checksums on success.
	 */
	private function get_plugin_checksums( $plugin, $version ) {
		$url = str_replace(
			array(
				'{slug}',
				'{version}',
			),
			array(
				$plugin,
				$version,
			),
			$this->url_template
		);

		$options = array(
			'timeout' => 30,
		);

		$headers  = array(
			'Accept' => 'application/json',
		);
		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			return false;
		}

		$body = trim( $response->body );
		$body = json_decode( $body, true );

		if ( ! is_array( $body ) || ! isset( $body['files'] ) || ! is_array( $body['files'] ) ) {
			return false;
		}

		return $body['files'];
	}

	/**
	 * Get the list of files that are part of the given plugin.
	 *
	 * @param string $path Relative path to the main plugin file.
	 *
	 * @return array<string> Array of files with their relative paths.
	 */
	private function get_plugin_files( $path ) {
		// TODO: Fetch and return the lift of plugin files.
		return array();
	}

	/**
	 * Check the integrity of a single plugin file by comparing it to the
	 * officially provided checksum.
	 *
	 * @param string $path      Relative path to the plugin file to check the
	 *                          integrity of.
	 * @param array  $checksums Array of provided checksums to compare against.
	 *
	 * @return true|string
	 */
	private function check_file( $path, $checksums ) {
		// TODO: Compute the checksum and compare it to the provided one.
		return true;
	}
}
