<?php

namespace Tarosky\Wpcli\Checksum;

/**
 * Base command that all checksum commands rely on.
 */
class Result {

	const MAX_ERROR_FILES = 20;

	private $name        = null;
	private $verified    = true;
	private $added       = [];
	private $missing     = [];
	private $mismatch    = [];
	private $noalgorithm = [];
	private $reason      = null;
	private $message     = null;

	public function set_name( $name ) {
		$this->name = $name;
	}

	public function set_message( $message ) {
		$this->verified = false;
		$this->reason   = $message;
	}

	public function set_reason( $reason ) {
		$this->verified = false;
		$this->reason   = $reason;
	}

	public function add_missing_file( $file ) {
		$this->verified  = false;
		$this->missing[] = $file;
	}

	public function add_added_file( $file ) {
		$this->verified = false;
		$this->added[]  = $file;
	}

	public function add_mismatch_file( $file ) {
		$this->verified   = false;
		$this->mismatch[] = $file;
	}

	public function add_noalgorithm_file( $file ) {
		$this->verified      = false;
		$this->noalgorithm[] = $file;
	}

	public function get() {
		$result = [];

		if ( null !== $this->name ) {
			$result['name'] = $this->name;
		}

		$result['verified'] = $this->verified;

		if ( null !== $this->message ) {
			$result['message'] = $this->message;
		}

		if ( null !== $this->reason ) {
			$result['reason'] = $this->reason;
		}

		if ( count( $this->added ) ) {
			sort( $this->added );
			$result['added'] = array_slice( $this->added, 0, self::MAX_ERROR_FILES );
		}

		if ( count( $this->mismatch ) ) {
			sort( $this->mismatch );
			$result['mismatch'] = array_slice( $this->mismatch, 0, self::MAX_ERROR_FILES );
		}

		if ( count( $this->missing ) ) {
			sort( $this->missing );
			$result['missing'] = array_slice( $this->missing, 0, self::MAX_ERROR_FILES );
		}

		if ( count( $this->noalgorithm ) ) {
			sort( $this->noalgorithm );
			$result['noalgorithm'] = array_slice( $this->noalgorithm, 0, self::MAX_ERROR_FILES );
		}

		return $result;
	}
}
