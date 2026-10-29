<?php

namespace App\Support;

use App\Models\TenantAuditEvent;
use Illuminate\Http\Request;

final class TenantAudit
{
    public static function record(Request $request, int $tenantId, string $action, ?string $resourceType = null, ?int $resourceId = null, array $context = []): TenantAuditEvent
    {
        return TenantAuditEvent::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'redacted_context' => $context,
            'trace_id' => $request->header('X-Trace-Id') ?: bin2hex(random_bytes(12)),
            'occurred_at' => now(),
        ]);
    }
}