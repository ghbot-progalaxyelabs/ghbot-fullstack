#!/usr/bin/env php
<?php

/**
 * Deployment Script
 *
 * Pulls latest code, builds, and restarts services
 * Run via: php deploy.php --id=deploy-123 --config=/path/to/config.json
 */

require_once __DIR__ . '/DeploymentLogger.php';

// Parse command line arguments
$options = getopt('', ['id:', 'config:']);

if (!isset($options['id']) || !isset($options['config'])) {
    fwrite(STDERR, "Usage: php deploy.php --id=<deployment-id> --config=<config-path>\n");
    exit(1);
}

$deploymentId = $options['id'];
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

// Initialize logger
$logger = new DeploymentLogger($config['log_file'], $deploymentId);
$logger->info('Deployment started');

// Check for lock file (prevent concurrent deployments)
$lockFile = $config['lock_file'];
$lockHandle = fopen($lockFile, 'c');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $logger->warning('Deployment already in progress, aborting');
    fclose($lockHandle);
    exit(0);
}

// Register shutdown function to release lock
register_shutdown_function(function() use ($lockHandle, $logger) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    $logger->info('Lock released');
});

try {
    $startTime = microtime(true);

    // Change to repo directory
    $repoPath = $config['repo_path'];
    if (!chdir($repoPath)) {
        throw new Exception("Failed to change directory to: $repoPath");
    }
    $logger->info('Changed to repo directory', ['path' => $repoPath]);

    // Git pull
    $logger->info('Starting git pull');
    exec("git pull origin {$config['branch']} 2>&1", $gitOutput, $gitReturnCode);

    if ($gitReturnCode !== 0) {
        throw new Exception("Git pull failed: " . implode("\n", $gitOutput));
    }

    $logger->info('Git pull completed', ['output' => implode(' | ', $gitOutput)]);

    // Run build command
    $logger->info('Starting build', ['command' => $config['build_command']]);
    exec("{$config['build_command']} 2>&1", $buildOutput, $buildReturnCode);

    if ($buildReturnCode !== 0) {
        throw new Exception("Build failed: " . implode("\n", $buildOutput));
    }

    $logger->info('Build completed successfully');

    // Restart services
    $logger->info('Restarting containers', ['command' => $config['restart_command']]);
    exec("{$config['restart_command']} 2>&1", $restartOutput, $restartReturnCode);

    if ($restartReturnCode !== 0) {
        throw new Exception("Container restart failed: " . implode("\n", $restartOutput));
    }

    $logger->info('Containers restarted');

    // Health checks
    $logger->info('Starting health checks');
    sleep(3); // Give services time to start

    $healthChecksPassed = 0;
    $totalHealthChecks = count($config['health_check_urls']);

    foreach ($config['health_check_urls'] as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            $healthChecksPassed++;
            $logger->info('Health check passed', ['url' => $url, 'status' => $httpCode]);
        } else {
            $logger->warning('Health check failed', ['url' => $url, 'status' => $httpCode]);
        }
    }

    if ($healthChecksPassed === 0) {
        throw new Exception("All health checks failed");
    }

    $logger->info("Health checks completed: $healthChecksPassed/$totalHealthChecks services responding");

    // Calculate duration
    $duration = round(microtime(true) - $startTime, 2);
    $logger->success("Deployment completed in {$duration}s");

} catch (Exception $e) {
    $logger->error('Deployment failed', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
