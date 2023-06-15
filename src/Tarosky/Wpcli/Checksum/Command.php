<?php

namespace Tarosky\Wpcli\Checksum;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI_Command;

/**
 * Base command that all checksum commands rely on.
 */
class Command extends WP_CLI_Command {

	const MAX_ERROR_FILES = 20;

	/**
	 * Normalizes directory separators to slashes.
	 *
	 * @param string $path Path to convert.
	 *
	 * @return string Path with all backslashes replaced by slashes.
	 */
	public static function normalize_directory_separators( $path ) {
		return str_replace( '\\', '/', $path );
	}

	/**
	 * Recursively get the list of files for a given path.
	 *
	 * @param string $path Root path to start the recursive traversal in.
	 *
	 * @return array<string>
	 */
	protected function get_files( $path ) {
		$filtered_files = array();

		$files = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator(
					$path,
					RecursiveDirectoryIterator::SKIP_DOTS
				),
				function ( $current, $key, $iterator ) use ( $path ) {
					return $this->filter_file( self::normalize_directory_separators( substr( $current->getPathname(), strlen( $path ) ) ) );
				}
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file_info ) {
			if ( $file_info->isFile() ) {
				$filtered_files[] = self::normalize_directory_separators( substr( $file_info->getPathname(), strlen( $path ) ) );
			}
		}

		return $filtered_files;
	}

	/**
	 * Whether to include the file in the verification or not.
	 *
	 * Can be overridden in subclasses.
	 *
	 * @param string $filepath Path to a file.
	 *
	 * @return bool
	 */
	protected function filter_file( $filepath ) {
		return true;
	}

	protected static function add_error_file( &$result, $group, $file ) {
		$result['verified'] = false;
		if ( array_key_exists( $group, $result ) && self::MAX_ERROR_FILES <= count( $result[ $group ] ) ) {
			return;
		}
		$result[ $group ][] = $file;
	}
}
