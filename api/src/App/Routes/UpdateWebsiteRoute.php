<?php

namespace App\Routes;

use Framework\IRouteHandler;
use Framework\ApiResponse;
use Framework\Database;
use Framework\Middleware\AuthMiddleware;
use App\Contracts\IUpdateWebsiteRoute;
use App\DTO\UpdateWebsiteRequest;
use App\DTO\UpdateWebsiteResponse;

class UpdateWebsiteRoute implements IRouteHandler, IUpdateWebsiteRoute
{
    public string $id;
    public ?string $name = null;
    public mixed $content = null;
    public mixed $settings = null;
    public ?string $userId = null;

    public function validation_rules(): array
    {
        return [
            'id' => 'required|uuid',
        ];
    }

    public function process(): ApiResponse
    {
        // Issue #6: Protect PUT /websites/:id with authentication
        $headers = getallheaders();
        $authenticatedUserId = AuthMiddleware::requireAuth($headers);

        // Convert content and settings to objects if they're arrays
        $content = is_array($this->content) ? (object)$this->content : $this->content;
        $settings = is_array($this->settings) ? (object)$this->settings : $this->settings;

        // Build request DTO from input
        $request = new UpdateWebsiteRequest(
            id: $this->id,
            name: $this->name,
            content: $content,
            settings: $settings,
            userId: $authenticatedUserId  // Use authenticated user ID
        );

        $response = $this->execute($request);

        return res_ok($response, 'Website updated successfully');
    }

    public function execute(UpdateWebsiteRequest $request): UpdateWebsiteResponse
    {
        // Get database connection
        $db = Database::getConnection();

        // First, verify the website exists and user has access
        $checkSql = "SELECT id FROM websites WHERE id = :id";
        $checkParams = ['id' => $request->id];

        if ($request->userId) {
            $checkSql .= " AND user_id = :user_id";
            $checkParams['user_id'] = $request->userId;
        }

        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute($checkParams);

        if (!$checkStmt->fetch()) {
            // Check if website exists at all
            $existsSql = "SELECT id FROM websites WHERE id = :id";
            $existsStmt = $db->prepare($existsSql);
            $existsStmt->execute(['id' => $request->id]);

            if ($existsStmt->fetch()) {
                throw new \Exception('Access denied: You do not own this website', 403);
            } else {
                throw new \Exception('Website not found', 404);
            }
        }

        // Build UPDATE query dynamically based on what fields are provided
        $updates = [];
        $params = ['id' => $request->id];

        if ($request->name !== null) {
            $updates[] = "name = :name";
            $params['name'] = $request->name;
        }

        if ($request->content !== null) {
            $updates[] = "content = :content::jsonb";
            $params['content'] = json_encode($request->content);
        }

        if ($request->settings !== null) {
            $updates[] = "settings = :settings::jsonb";
            $params['settings'] = json_encode($request->settings);
        }

        // Always update the updated_at timestamp
        $updates[] = "updated_at = CURRENT_TIMESTAMP";

        if (empty($updates)) {
            throw new \Exception('No fields to update', 400);
        }

        $sql = "
            UPDATE websites
            SET " . implode(', ', $updates) . "
            WHERE id = :id
            RETURNING id, updated_at
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \Exception('Failed to update website');
        }

        // Return response DTO
        return new UpdateWebsiteResponse(
            id: $result['id'],
            updatedAt: $result['updated_at']
        );
    }
}
