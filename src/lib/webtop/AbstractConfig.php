<?php

namespace WT;

abstract class AbstractConfig {
	
	protected $config;
	
	protected function __construct($file) {
		$this->config = new \Noodlehaus\Config($file);
	}
	
	protected function getValue($key, $defaultsMap = null) {
		if (!is_null($defaultsMap)) {
			return $this->config->get($key, array_key_exists($key, $defaultsMap) ? $defaultsMap[$key] : null);
		} else {
			return $this->config->get($key);
		}
	}
}
