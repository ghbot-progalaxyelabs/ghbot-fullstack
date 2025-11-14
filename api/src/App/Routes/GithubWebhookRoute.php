<?php

namespace App\Routes;

use Framework\IRouteHandler;
use Framework\ApiResponse;
use Framework\Env;

class GithubWebhookRoute implements IRouteHandler
{
    public function validation_rules(): array
    {
        return [];
    }

    public function process(): ApiResponse
    {
        // Get raw request body for signature verification
        $payload = file_get_contents('php://input');

        // Verify GitHub webhook signature
        if (!$this->verifySignature($payload)) {
            http_response_code(401);
            return res_error('Invalid signature', 401);
        }

        // Parse JSON payload
        $data = json_decode($payload, true);
        if (!$data) {
            http_response_code(400);
            return res_error('Invalid JSON payload', 400);
        }

        // Verify this is a push to main branch
        if (!isset($data['ref']) || $data['ref'] !== 'refs/heads/main') {
            // Not a push to main, ignore silently
            return res_ok([], 'Ignored: not a push to main branch');
        }

        // Generate unique deployment ID
        $deploymentId = $this->generateDeploymentId();

        // Add to deployment queue
        $queueAdded = $this->addToQueue($deploymentId);

        if (!$queueAdded) {
            http_response_code(500);
            return res_error('Failed to add deployment to queue', 500);
        }

        // Log webhook receipt
        $this->logWebhook($deploymentId, $data);

        return res_ok([
            'deployment_id' => $deploymentId,
            'status' => 'queued'
        ], 'Deployment queued successfully');
    }

    /**
     * Verify GitHub webhook signature using HMAC-SHA256
     */
    private function verifySignature(string $payload): bool
    {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        if (empty($signature)) {
            return false;
        }

        $env = Env::get_instance();
        $secret = $env->GITHUB_WEBHOOK_SECRET;

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Generate unique deployment ID
     */
    private function generateDeploymentId(): string
    {
        return sprintf(
            'deploy-%s-%s',
            date('Ymd-His'),
            substr(bin2hex(random_bytes(4)), 0, 8)
        );
    }

    /**
     * Add deployment ID to queue file
     */
    private function addToQueue(string $deploymentId): bool
    {
        // Determine queue file path
        $homeDir = getenv('HOME') ?: '/home/' . get_current_user();
        $queueFile = $homeDir . '/deploy/queue.txt';

        // Append to queue file with exclusive lock
        $result = file_put_contents(
            $queueFile,
            $deploymentId . "\n",
            FILE_APPEND | LOCK_EX
        );

        return $result !== false;
    }

    /**
     * Log webhook receipt
     */
    private function logWebhook(string $deploymentId, array $data): void
    {
        $homeDir = getenv('HOME') ?: '/home/' . get_current_user();
        $logFile = $homeDir . '/deploy/deployment.log';

        $timestamp = date('Y-m-d H:i:s');
        $commitHash = substr($data['after'] ?? 'unknown', 0, 7);
        $pusher = $data['pusher']['name'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] [%s] [INFO] Webhook received from GitHub (commit: %s, pusher: %s)\n",
            $timestamp,
            $deploymentId,
            $commitHash,
            $pusher
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
