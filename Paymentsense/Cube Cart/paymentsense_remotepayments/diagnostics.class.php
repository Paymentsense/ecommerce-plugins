<?php

/**
 * Diagnostics module information.
 *
 * Web:   http://www.paymentsense.com
 * Email:  devsupport@paymentsense.com
 * License:  GPL-3.0 http://opensource.org/licenses/GPL-3.0
 */

/**
 * Provides information about installed module to check integrity of files.
 */
class Diagnostics {
	/**
	 * Module name
	 */
	public const MODULE_NAME = 'Paymentsense - Remote Payments for CubeCart';
	/**
	 * Plugin extension.
	 */
	public const MODULE_VERSION = '1.0.0';
	/**
	 * Info action query parameters.
	 */
	public const PARAM_ACTION_INFO = 'info';
	/**
	 * Checksums action in query parameters.
	 */
	public const PARAM_ACTION_CHECKSUMS = 'checksums';
	/**
	 * Action parameter in query parameters.
	 */
	public const PARAM_ACTION = 'action';
	/**
	 * Extended info parameter in query parameters.
	 */
	public const PARAM_EXTENDED_INFO = 'extended_info';
	/**
	 * File list key in query parameters.
	 */
	public const PARAM_FILE_LIST = 'data';
	/**
	 * Output format key in query parameters.
	 */
	public const PARAM_OUTPUT_FORMAT = 'output';
	/**
	 * Value that indicates extended info is being requested.
	 */
	public const EXTENDED_INFO_TRUE = 'true';
	/**
	 * Json output value in query parameters.
	 */
	public const OUTPUT_JSON = 'json';
	/**
	 * Text output value in query parameters.
	 */
	public const OUTPUT_TEXT = 'text';
	/**
	 * Content type header.
	 */
	public const HEADER_CONTENT_TYPE = 'Content-Type: ';
	/**
	 * Content type text.
	 */
	public const HEADER_CONTENT_TYPE_TEXT_PLAIN = 'text/plain';
	/**
	 * Content type json.
	 */
	public const HEADER_CONTENT_TYPE_APPLICATION_JSON = 'application/json';

	/**
	 * Action requested.
	 *
	 * @var string|null
	 */
	private $_action;
	/**
	 * Data that will be sent in the output.
	 *
	 * @var array
	 */
	private $_outputData = [];
	/**
	 * Format to send the output in. text, json and so on.
	 *
	 * @var string|null
	 */
	private $_outputFormat;
	/**
	 * Output that will be sent to the client for presentation.
	 *
	 * @var string
	 */
	private $_output = '';
	/**
	 * List of files for which the checksums are requested.
	 *
	 * @var array
	 */
	private $_checksumsFileList = [];

	/**
	 * Executes action.
	 */
	public function executeAction(): void {
		$this->_resolveAction();
		if ($this->_isActionInfo()) {
			$this->_executeInfo();
		}

		if ($this->_isActionChecksums()) {
			$this->_executeChecksums();
		}

		$this->_encodeOutput();
		$this->_createResponse();
	}

	/**
	 * Resolves action that needs to be performed from the request.
	 */
	private function _resolveAction() {
		$action = $_GET[self::PARAM_ACTION] ?? '';
		$available_actions = [static::PARAM_ACTION_INFO, static::PARAM_ACTION_CHECKSUMS];

		if (null === $action || !in_array($action, $available_actions)) {
			trigger_error('Action unknown');
			http_response_code(400);
			return;
		}

		$this->_action = $action;
	}

	/**
	 * Checks if action is info.
	 */
	private function _isActionInfo(): bool {
		return static::PARAM_ACTION_INFO === $this->_action;
	}

	/**
	 * Checks if action is for checksums.
	 */
	private function _isActionChecksums(): bool {
		return static::PARAM_ACTION_CHECKSUMS === $this->_action;
	}

	/**
	 * Executes info action.
	 */
	private function _executeInfo() {
		if ($this->_isRequestingExtendedInfo()) {
			$this->_createExtendedInfoOutputData();
			return;
		}

		$this->_createInfoOutputData();
	}

	/**
	 * Checks if the request contains extended info param set to true.
	 */
	private function _isRequestingExtendedInfo(): bool {
		if (!isset($_GET[self::PARAM_EXTENDED_INFO])) {
			return false;
		}

		return static::EXTENDED_INFO_TRUE === $_GET[self::PARAM_EXTENDED_INFO] ?? 'false';
	}

	/**
	 * Creates output data for extended info request.
	 */
	private function _createExtendedInfoOutputData() {
		$this->_outputData = [
			'Module Name' => self::MODULE_NAME,
			'Module Installed Version' => self::MODULE_VERSION,
			'CubeCart Version' => CC_VERSION,
			'PHP Version' => phpversion(),
		];
	}

	/**
	 * Creates output data for simple info request.
	 */
	private function _createInfoOutputData() {
		$this->_outputData = [
			'Module Name' => self::MODULE_NAME,
			'Module Installed Version' => self::MODULE_VERSION,
		];
	}

	/**
	 * Executes a checksum request.
	 */
	private function _executeChecksums() {
		$this->_resolveFileList();
		$this->_createChecksums();
	}

	/**
	 * Resolves file list for which checksums to be generated.
	 */
	private function _resolveFileList() {
		if (!isset($_POST[static::PARAM_FILE_LIST])) {
			return;
		}

		$data = $_POST[static::PARAM_FILE_LIST];
		if (!is_array($data)) {
			return;
		}

		$this->_checksumsFileList = $data;
	}

	/**
	 * Calculates and add checksums for the given files.
	 */
	private function _createChecksums() {
		foreach ($this->_checksumsFileList as $key => $file) {
			$filename = __DIR__ . DIRECTORY_SEPARATOR . $file;
			$this->_outputData['Checksums'][$key] = $this->_calculateFileChecksum($filename);
		}
	}

	/**
	 * Calculates checksum for given file.
	 */
	private function _calculateFileChecksum(string $file_path): ?string {
		return is_file($file_path)
			? sha1_file($file_path)
			: null;
	}

	/**
	 * Encodes the output based on the value of output param in url.
	 */
	private function _encodeOutput() {
		$this->_resolveOutputFormat();
		if (static::OUTPUT_JSON === $this->_outputFormat) {
			$this->_encodeOutputAsJson();
			return;
		}

		if (static::OUTPUT_TEXT === $this->_outputFormat) {
			$this->_encodeOutputAsText();
			return;
		}

		trigger_error('Unknown output requested');
	}

	/**
	 * Resolves the output format from the parameter output.
	 */
	private function _resolveOutputFormat() {
		$this->_outputFormat = $_GET[self::PARAM_OUTPUT_FORMAT] ?? static::OUTPUT_TEXT;
	}

	/**
	 * Encodes output as json.
	 */
	private function _encodeOutputAsJson() {
		$this->_output = json_encode($this->_outputData);
	}

	/**
	 * Encodes output as text.
	 */
	private function _encodeOutputAsText() {
		$this->_output = $this->_convertArrayToString($this->_outputData);
	}

	/**
	 * Converts array to a string representation.
	 */
	private function _convertArrayToString($arr, $indent = ''): string {
		$result = '';
		$indent_pattern = '  ';
		foreach ($arr as $key => $value) {
			if ('' !== $result) {
				$result .= PHP_EOL;
			}
			if (is_array($value)) {
				$value = PHP_EOL . $this->_convertArrayToString($value, $indent . $indent_pattern);
			}
			$result .= $indent . $key . ': ' . $value;
		}
		return $result;
	}

	/**
	 * Sets the appropriate headers and prints the output prepared.
	 */
	private function _createResponse() {
		header('Cache-Control:', 'max-age=0, must-revalidate, no-cache, no-store');
		header('Pragma:', 'no-cache');

		if (static::OUTPUT_JSON === $this->_outputFormat) {
			header(static::HEADER_CONTENT_TYPE . static::HEADER_CONTENT_TYPE_APPLICATION_JSON);
		}
		if (static::OUTPUT_TEXT === $this->_outputFormat) {
			header(static::HEADER_CONTENT_TYPE . static::HEADER_CONTENT_TYPE_TEXT_PLAIN);
		}

		echo $this->_output;
	}
}
