<?php

namespace App\Services\Support;

use App\Models\Support\SupportSpecialistAgent;
use App\Models\Support\SupportContract;

final class ContractResolver
{
    /**
     * Resolves the aggregated effective contract permissions for a given specialist agent.
     */
    public static function resolve(SupportSpecialistAgent $agent): array
    {
        $allowedTools = [];
        $readPermissions = [];
        $actionPermissions = [];
        $forbiddenActions = [];
        $escalationRules = [];

        $contracts = $agent->contracts()->where('is_enabled', true)->get();

        foreach ($contracts as $c) {
            $allowedTools = array_merge($allowedTools, (array)($c->allowed_tools ?? []));
            $readPermissions = array_merge($readPermissions, (array)($c->read_permissions ?? []));
            $actionPermissions = array_merge($actionPermissions, (array)($c->action_permissions ?? []));
            $forbiddenActions = array_merge($forbiddenActions, (array)($c->forbidden_actions ?? []));
            $escalationRules = array_merge($escalationRules, (array)($c->escalation_rules ?? []));
        }

        return [
            'agent_id' => $agent->id,
            'slug' => $agent->slug,
            'agent_type' => $agent->agent_type,
            'allowed_tools' => array_values(array_unique($allowedTools)),
            'read_permissions' => array_values(array_unique($readPermissions)),
            'action_permissions' => array_values(array_unique($actionPermissions)),
            'forbidden_actions' => array_values(array_unique($forbiddenActions)),
            'escalation_rules' => array_values(array_unique($escalationRules)),
        ];
    }
}
