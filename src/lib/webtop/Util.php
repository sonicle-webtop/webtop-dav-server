<?php

namespace WT;

class Util {
	
	/*
	 * Clear all levels of output buffering
	 * 
	 * @return void
	 */
	public static function obEnd() {
		while (ob_get_level()) {
			ob_end_clean();
		}
	}
	
	public static function readVersion() {
		$file = 'VERSION';
		$version = '0.0.0.0';
		if (file_exists($file)) {
			$version = fgets(fopen($file, 'r'));
		}
		return $version;
	}
	
	public static function stripLeadingDirSeparator($path, $separator = DIRECTORY_SEPARATOR) {
		if ($path === $separator) {
			return $path;
		}
		return is_null($path) ? $path : ltrim($path, $separator);
	}
	
	public static function stripTrailingDirSeparator($path, $separator = DIRECTORY_SEPARATOR) {
		if ($path === $separator) {
			return $path;
		}
		return is_null($path) ? $path : rtrim($path, $separator);
	}
	
	public static function stripDirSeparator($path, $separator = DIRECTORY_SEPARATOR) {
		if ($path === $separator) {
			return $path;
		}
		return is_null($path) ? $path : trim($path, $separator);
	}
	
	/*
	public static function parseRfc822($rfc822) {
		if (function_exists('imap_rfc822_parse_adrlist')) {
			$arr = imap_rfc822_parse_adrlist($rfc822, '');
			if (!is_array($arr) || count($arr) < 1) return null;
			
			$firstAdr = array_shift($arr);
			$address = null;
			if (!empty($firstAdr->mailbox) && ($firstAdr->mailbox !== 'INVALID_ADDRESS') && !empty($firstAdr->host)) {
				$address = $firstAdr->mailbox.'@'.$firstAdr->host;
			}
			return [
				'address' => $address,
				'personal' => $firstAdr->personal
			];
		}
		
		//$logger->debug('{} Warning : php-imap not available', [__METHOD__]);
		return null;		
	}
	*/
	
	/**
	 * Guess MIME type of a picture by picture file signature.
	 * 
	 * @param string $data Image data (at least the first 4 bytes)
	 * @return string MIME type
	 */
	public static function guessImageMediaType($data) {
		if (substr($data, 0, 4) == '\x47\x49\x46\x38') {
			return('image/gif');
		} else if (substr($data, 0, 4) == '\xFF\xD8\xFF\xE0') {
			return('image/jpg');
		} else if (substr($data, 0, 4) == '\x89\x50\x4E\x47') {
			return('image/png');
		} else {
			return('image/unknown');
		}
	}
}
