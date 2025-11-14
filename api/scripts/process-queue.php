#!/usr/bin/env php
<?php

/**
 * Process Deployment Queue
 *
 * Reads queue.txt and schedules deployment jobs via `at` command
 * Run via cron: * * * * * php process-queue.php --config=/path/to/config.json
 */

require_once __DIR__ . '/DeploymentLogger.php';

// Parse command line arguments
$options = getopt('', ['config:']);

if (!isset($options['config'])) {
    fwrite(STDERR, "Usage: php process-queue.php --config=<config-path>\n");
    exit(1);
}

$configPath = $options['config'];

// Load configuration
if (!file_exists($configPath)) {
    fwrite(STDERR, "Config file not found: $configPath\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    fwrite(STDERR, "Invalid JSON in config file: $configPath\n");
    exit(1);
}

// Determine queue file path (same directory as config)
$deployDir = dirname($configPath);
$queueFile = $deployDir . '/queue.txt';

// Check if queue file exists
if (!file_exists($queueFile)) {
    // No queue file, nothing to process
    exit(0);
}

// Read queue with exclusive lock
$queueHandle = fopen($queueFile, 'r+');
if (!$queueHandle) {
    fwrite(STDERR, "Failed to open queue file: $queueFile\n");
    exit(1);
}

// Lock the queue file
if (!flock($queueHandle, LOCK_EX)) {
    fwrite(STDERR, "Failed to lock queue file\n");
    fclose($queueHandle);
    exit(1);
}

// Read all deployment IDs
$deploymentIds = [];
while (($line = fgets($queueHandle)) !== false) {
    $deploymentId = trim($line);
    if (!empty($deploymentId)) {
        $deploymentIds[] = $deploymentId;
    }
}

// Clear the queue
ftruncate($queueHandle, 0);
rewind($queueHandle);

// Release lock and close
flock($queueHandle, LOCK_UN);
fclose($queueHandle);

// Process each deployment ID
foreach ($deploymentIds as $deploymentId) {
    $logger = new DeploymentLogger($config['log_file'], $deploymentId);
    $logger->info('Processing deployment from queue');

    // Build the deployment command
    $deployScriptPath = __DIR__ . '/deploy.php';
    $deployCommand = sprintf(
        '/usr/bin/php %s --id=%s --config=%s >> %s 2>&1',
        escapeshellarg($deployScriptPath),
        escapeshellarg($deploymentId),
        escapeshellarg($configPath),
        escapeshellarg($config['log_file'])
    );

    // Schedule via `at` command (runs immediately)
    $atCommand = sprintf('echo %s | at now 2>&1', escapeshellarg($deployCommand));
    exec($atCommand, $atOutput, $atReturnCode);

    if ($atReturnCode === 0) {
        $logger->info('Deployment scheduled via at command', ['output' => implode(' | ', $atOutput)]);
    } else {
        $logger->error('Failed to schedule deployment', [
            'error' => implode(' | ', $atOutput),
            'return_code' => $atReturnCode
        ]);
    }
}

exit(0);
