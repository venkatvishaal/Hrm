<?php
namespace App\Services;

use App\Core\Database;

class NotificationService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Notify a single employee via their preferred channel(s).
     */
    public function notifyEmployee(int $employeeId, string $title, string $message, string $eventType): void
    {
        $employee = $this->db->fetch(
            'SELECT notification_preference FROM employees WHERE id = :id',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return;
        }
        $channels = $this->channelsFromPreference((string)($employee['notification_preference'] ?: 'Dashboard'));
        $this->bulkInsertNotifications([['id' => $employeeId, 'channels' => $channels]], $title, $message, $eventType);
    }

    /**
     * Notify all employees who hold a given role.
     * Uses a single aggregate query + one bulk INSERT instead of N+1 round-trips.
     */
    public function notifyRole(string $role, string $title, string $message, string $eventType): void
    {
        $this->notifyRoles([$role], $title, $message, $eventType);
    }

    /**
     * Notify employees across multiple roles using one DB round-trip per call.
     */
    public function notifyRoles(array $roles, string $title, string $message, string $eventType): void
    {
        if (!$roles) {
            return;
        }
        $roles = array_values(array_unique($roles));

        // Build parameterised IN clause.
        $placeholders = implode(',', array_map(static fn($i) => ':role' . $i, array_keys($roles)));
        $params       = [];
        foreach ($roles as $i => $role) {
            $params['role' . $i] = $role;
        }

        // Single query — fetch all matching employees with their preferences.
        $employees = $this->db->fetchAll(
            "SELECT e.id, e.notification_preference
             FROM employees e
             JOIN users u ON u.id = e.user_id
             WHERE u.role IN ($placeholders)",
            $params
        );

        if (!$employees) {
            return;
        }

        // Map each employee to their resolved channels.
        $targets = array_map(
            fn(array $emp) => [
                'id'       => (int)$emp['id'],
                'channels' => $this->channelsFromPreference((string)($emp['notification_preference'] ?: 'Dashboard')),
            ],
            $employees
        );

        $this->bulkInsertNotifications($targets, $title, $message, $eventType);
    }

    /**
     * Return unread notifications for a user (joined through employees).
     */
    public function unreadForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT n.*
             FROM notifications n
             JOIN employees e ON e.id = n.employee_id
             WHERE e.user_id = :user_id
               AND n.is_read = 0
             ORDER BY n.created_at DESC',
            ['user_id' => $userId]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Performs a single multi-row INSERT for all employee × channel combinations.
     *
     * @param array<array{id:int,channels:list<string>}> $targets
     */
    private function bulkInsertNotifications(array $targets, string $title, string $message, string $eventType): void
    {
        $valueClauses = [];
        $params       = [];
        $i            = 0;

        foreach ($targets as $target) {
            foreach ($target['channels'] as $channel) {
                $valueClauses[] = "(:eid{$i}, :ch{$i}, :title{$i}, :msg{$i}, :evt{$i}, 0, NOW())";
                $params["eid{$i}"]   = $target['id'];
                $params["ch{$i}"]    = $channel;
                $params["title{$i}"] = $title;
                $params["msg{$i}"]   = $message;
                $params["evt{$i}"]   = $eventType;
                $i++;
            }
        }

        if (!$valueClauses) {
            return;
        }

        // PDO parameter limit (~65 535). Chunk large batches.
        $chunkSize = 1000; // rows at a time (5 params each = 5000 params per batch)
        foreach (array_chunk($valueClauses, $chunkSize) as $chunkIndex => $chunk) {
            // Extract matching params for this chunk.
            $chunkParams = [];
            $offset      = $chunkIndex * $chunkSize;
            for ($j = $offset; $j < $offset + count($chunk); $j++) {
                foreach (['eid', 'ch', 'title', 'msg', 'evt'] as $prefix) {
                    if (isset($params["{$prefix}{$j}"])) {
                        $chunkParams["{$prefix}{$j}"] = $params["{$prefix}{$j}"];
                    }
                }
            }
            $this->db->execute(
                'INSERT INTO notifications (employee_id, channel, title, message, event_type, is_read, created_at) VALUES ' .
                implode(', ', $chunk),
                $chunkParams
            );
        }
    }

    /**
     * Map a notification preference setting to the resolved delivery channels.
     *
     * @return list<string>
     */
    private function channelsFromPreference(string $pref): array
    {
        return match ($pref) {
            'Email'     => ['Email'],
            'WhatsApp'  => ['WhatsApp'],
            'All'       => ['Dashboard', 'Email', 'WhatsApp'],
            default     => ['Dashboard'],
        };
    }
}
