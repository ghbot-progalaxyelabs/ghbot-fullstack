<?php

/**
 * DeploymentLogger
 *
 * Simple file-based logger for deployment operations
 */
class DeploymentLogger {
    private string $logFile;
    private string $deploymentId;

    public function __construct(string $logFile, string $deploymentId) {
        $this->logFile = $logFile;
        $this->deploymentId = $deploymentId;
    }

    /**
     * Log a message with level and optional context
     *
     * @param string $level Log level (INFO, WARNING, ERROR, SUCCESS)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        $entry = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            $timestamp,
            $this->deploymentId,
            strtoupper($level),
            $message,
            $contextStr
        );

        // Append to log file with exclusive lock
        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log info level message
     */
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log warning level message
     */
    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log error level message
     */
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log success level message
     */
    public function success(string $message, array $context = []): void {
        $this->log('SUCCESS', $message, $context);
    }
}
