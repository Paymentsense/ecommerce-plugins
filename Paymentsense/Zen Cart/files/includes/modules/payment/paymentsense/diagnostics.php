<?php

/**
 * Diagnostics module information for debugging purposes.
 *
 * @license GNU Public License V2.0
 */

/**
 * Provides information about installed module to check integrity of files.
 */
class diagnostics
{
    /**
     * Module name
     */
    public const MODULE_NAME = 'Paymentsense - Remote Payments for Zen Cart';
    /**
     * Plugin extension.
     */
    public const MODULE_VERSION = '1.0.1';
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
    private $action;
    /**
     * Data that will be sent in the output.
     *
     * @var array
     */
    private $outputData = [];
    /**
     * Format to send the output in. text, json and so on.
     *
     * @var string|null
     */
    private $outputFormat;
    /**
     * Output that will be sent to the client for presentation.
     *
     * @var string
     */
    private $output = '';
    /**
     * List of files for which the checksums are requested.
     *
     * @var array
     */
    private $checksumsFileList = [];

    /**
     * Executes action.
     */
    public function executeAction()
    {
        $this->includeRequiredFiles();
        $this->resolveAction();
        if ($this->isActionInfo()) {
            $this->executeInfo();
        }

        if ($this->isActionChecksums()) {
            $this->executeChecksums();
        }

        $this->encodeOutput();
        $this->createResponse();

        return $this;
    }

    /**
     * Include required files needed to fulfill a diagnostics request.
     */
    private function includeRequiredFiles()
    {
        require_once 'includes/configure.php';
        require_once 'includes/defined_paths.php';
        require_once 'includes/version.php';
    }

    /**
     * Resolves action that needs to be performed from the request.
     */
    private function resolveAction()
    {
        $action = $_GET[self::PARAM_ACTION] ?? '';
        $available_actions = [static::PARAM_ACTION_INFO, static::PARAM_ACTION_CHECKSUMS];

        if (null === $action || !in_array($action, $available_actions)) {
            trigger_error('Action unknown');
            http_response_code(500);
            return;
        }

        $this->action = $action;
    }

    /**
     * Checks if action is info.
     */
    private function isActionInfo(): bool
    {
        return static::PARAM_ACTION_INFO === $this->action;
    }

    /**
     * Checks if action is for checksums.
     */
    private function isActionChecksums(): bool
    {
        return static::PARAM_ACTION_CHECKSUMS === $this->action;
    }

    /**
     * Executes info action.
     */
    private function executeInfo()
    {
        if ($this->isRequestingExtendedInfo()) {
            $this->createExtendedInfoOutputData();
            return;
        }

        $this->createInfoOutputData();
    }

    /**
     * Checks if the request contains extended info param set to true.
     */
    private function isRequestingExtendedInfo(): bool
    {
        if (!isset($_GET[self::PARAM_EXTENDED_INFO])) {
            return false;
        }

        return static::EXTENDED_INFO_TRUE === $_GET[self::PARAM_EXTENDED_INFO] ?? 'false';
    }

    /**
     * Creates output data for extended info request.
     */
    private function createExtendedInfoOutputData()
    {
        $this->outputData = [
            'Module Name' => self::MODULE_NAME,
            'Module Installed Version' => self::MODULE_VERSION,
            'Zen Cart Version' => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR,
            'PHP Version' => phpversion(),
        ];
    }

    /**
     * Creates output data for simple info request.
     */
    private function createInfoOutputData()
    {
        $this->outputData = [
            'Module Name' => self::MODULE_NAME,
            'Module Installed Version' => self::MODULE_VERSION,
        ];
    }

    /**
     * Executes a checksum request.
     */
    private function executeChecksums()
    {
        $this->resolveFileList();
        $this->createChecksums();
    }

    /**
     * Resolves file list for which checkums to be generated.
     */
    private function resolveFileList()
    {
        if (!isset($_POST[static::PARAM_FILE_LIST])) {
            return;
        }

        $data = $_POST[static::PARAM_FILE_LIST];
        if (!is_array($data)) {
            return;
        }

        $this->checksumsFileList = $data;
    }

    /**
     * Calculates and add checksums for the given files.
     *
     * @return $this
     */
    public function createChecksums(): self
    {
        foreach ($this->checksumsFileList as $key => $file) {
            $filename = DIR_FS_CATALOG . DIRECTORY_SEPARATOR . $file;
            $this->outputData['Checksums'][$key] = $this->calculateFileChecksum($filename);
        }

        return $this;
    }

    /**
     * Calculates checksum for given file.
     */
    private function calculateFileChecksum(string $file_path): ?string
    {
        return is_file($file_path)
            ? sha1_file($file_path)
            : null;
    }

    /**
     * Encodes the output based on the value of output param in url.
     */
    private function encodeOutput()
    {
        $this->resolveOutputFormat();
        if (static::OUTPUT_JSON === $this->outputFormat) {
            $this->encodeOutputAsJson();
            return;
        }

        if (static::OUTPUT_TEXT === $this->outputFormat) {
            $this->encodeOutputAsText();
            return;
        }

        trigger_error('Unknown output requested');
    }

    /**
     * Resolves the output format from the parameter output.
     */
    private function resolveOutputFormat()
    {
        $this->outputFormat = $_GET[self::PARAM_OUTPUT_FORMAT] ?? static::OUTPUT_TEXT;
    }

    /**
     * Encodes output as json.
     */
    public function encodeOutputAsJson()
    {
        $this->output = json_encode($this->outputData);
    }

    /**
     * Encodes output as text.
     */
    public function encodeOutputAsText()
    {
        $this->output = $this->convertArrayToString($this->outputData);
    }

    /**
     * Converts array to a string representation.
     */
    private function convertArrayToString($arr, $indent = ''): string
    {
        $result = '';
        $indent_pattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }
            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $indent . $indent_pattern);
            }
            $result .= $indent . $key . ': ' . $value;
        }
        return $result;
    }

    /**
     * Sets the appropriate headers and prints the output prepared.
     */
    private function createResponse()
    {
        header('Cache-Control:', 'max-age=0, must-revalidate, no-cache, no-store');
        header('Pragma:', 'no-cache');

        if (static::OUTPUT_JSON === $this->outputFormat) {
            header(static::HEADER_CONTENT_TYPE . static::HEADER_CONTENT_TYPE_APPLICATION_JSON);
        }
        if (static::OUTPUT_TEXT === $this->outputFormat) {
            header(static::HEADER_CONTENT_TYPE . static::HEADER_CONTENT_TYPE_TEXT_PLAIN);
        }

        echo $this->output;
    }
}
