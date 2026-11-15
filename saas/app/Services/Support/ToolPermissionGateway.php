<?php

namespace App\Services\Support;

final class ToolPermissionGateway
{
    /**
     * Evaluates whether a tool call is authorized in the current execution context.
     *
     * @param string $toolName
     * @param array $context ['authenticated' => bool, 'client_id' => int|string, 'channel' => string, 'permissions' => array]
     * @param array $contractPolicy
     * @return array{decision: 'allow'|'deny', reason: string}
     */
    public static function decide(string $toolName, array $context, array $contractPolicy = []): array
    {
        $toolName = trim($toolName);
        if ($toolName === '') {
            return ['decision' => 'deny', 'reason' => 'Empty tool name.'];
        }

        // 1. Root / Critical Action Guards
        $forbiddenTools = [
            'database_drop', 'system_shell_exec', 'change_root_password',
            'delete_account', 'bypass_billing', 'modify_admin_users'
        ];
        if (in_array(strtolower($toolName), $forbiddenTools, true)) {
            return ['decision' => 'deny', 'reason' => 'Execution of destructive or root-level actions is strictly forbidden.'];
        }

        // 2. Check Contract Forbidden List
        $contractForbidden = (array)($contractPolicy['forbidden_actions'] ?? []);
        if (in_array($toolName, $contractForbidden, true)) {
            return ['decision' => 'deny', 'reason' => "Tool [{$toolName}] is explicitly forbidden by the agent's contract."];
        }

        // 3. Check Channel and Authentication Requirements
        if (str_starts_with($toolName, 'client_private_') || in_array($toolName, ['view_invoices', 'view_services', 'create_ticket'], true)) {
            if (empty($context['authenticated']) || empty($context['client_id'])) {
                return ['decision' => 'deny', 'reason' => "Authentication is strictly required to execute [{$toolName}]."];
            }
        }

        // 4. Check Allowed Tools in Contract (if defined)
        $allowedTools = (array)($contractPolicy['allowed_tools'] ?? []);
        if (!empty($allowedTools) && !in_array('*', $allowedTools, true) && !in_array($toolName, $allowedTools, true)) {
            return ['decision' => 'deny', 'reason' => "Tool [{$toolName}] is not in the agent's allowed tools list."];
        }

        return ['decision' => 'allow', 'reason' => 'Authorized by contract and channel policy.'];
    }
}
