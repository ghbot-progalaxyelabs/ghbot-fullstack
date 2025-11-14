<?php

namespace App\Routes;

use Framework\IRouteHandler;
use Framework\ApiResponse;
use Framework\Database;
use Framework\Middleware\AuthMiddleware;
use App\Contracts\IGetWebsiteRoute;
use App\DTO\GetWebsiteRequest;
use App\DTO\GetWebsiteResponse;

class GetWebsiteRoute implements IRouteHandler, IGetWebsiteRoute
{
    public string $id;
    public ?string $userId = null;

    public function validation_rules(): array
    {
        return [
            'id' => 'required|uuid',
        ];
    }

    public function process(): ApiResponse
    {
        // Issue #6: Protect GET /websites/:id with authentication
        $headers = getallheaders();
        $authenticatedUserId = AuthMiddleware::requireAuth($headers);

        // Build request DTO from input
        $request = new GetWebsiteRequest(
            id: $this->id,
            userId: $authenticatedUserId  // Use authenticated user ID
        );

        $response = $this->execute($request);

        return res_ok($response);
    }

    public function execute(GetWebsiteRequest $request): GetWebsiteResponse
    {
        // Get database connection
        $db = Database::getConnection();

        // Query website by ID
        $sql = "
            SELECT id, name, type, content, settings, status, created_at, updated_at
            FROM websites
            WHERE id = :id
        ";

        // If user is authenticated, validate ownership
        if ($request->userId) {
            $sql .= " AND user_id = :user_id";
        }

        $stmt = $db->prepare($sql);
        $params = ['id' => $request->id];

        if ($request->userId) {
            $params['user_id'] = $request->userId;
        }

        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            // Determine if website doesn't exist or user doesn't have access
            $checkSql = "SELECT id FROM websites WHERE id = :id";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute(['id' => $request->id]);

            if ($checkStmt->fetch()) {
                // Website exists but user doesn't have access
                throw new \Exception('Access denied: You do not own this website', 403);
            } else {
                // Website doesn't exist
                throw new \Exception('Website not found', 404);
            }
        }

        // Parse JSON fields
        $content = json_decode($result['content'] ?? '{}');
        $settings = json_decode($result['settings'] ?? '{}');

        // Return response DTO
        return new GetWebsiteResponse(
            id: $result['id'],
            name: $result['name'],
            type: $result['type'],
            content: $content,
            settings: $settings,
            status: $result['status'],
            createdAt: $result['created_at'],
            updatedAt: $result['updated_at']
        );
    }
}
