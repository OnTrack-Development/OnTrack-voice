<?php

namespace App\Services\Knowledge;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportLearningPattern;
use App\Models\Support\SupportLearningRun;

final class KnowledgeLearningEngine
{
    /**
     * Executes knowledge extraction on resolved tickets for an account.
     *
     * @param int $accountId
     * @return array Summary of the learning run
     */
    public function run(int $accountId): array
    {
        $run = SupportLearningRun::create([
            'account_id' => $accountId,
            'status' => 'processing',
            'discovered_count' => 0,
            'processed_count' => 0,
            'knowledge_version' => 'v' . date('Ymd.His'),
            'summary' => 'Learning run started.',
        ]);

        $resolvedTickets = SupportTicket::where('account_id', $accountId)
            ->whereIn('status', ['closed', 'resolved', 'answered'])
            ->latest()
            ->take(50)
            ->get();

        $run->update(['discovered_count' => $resolvedTickets->count()]);

        $patternsCreated = 0;
        foreach ($resolvedTickets as $ticket) {
            $subject = trim($ticket->subject);
            $message = trim($ticket->latest_message);

            if ($subject === '' || $message === '') continue;

            $sanitizedProblem = KnowledgeSanitizer::sanitize($subject . "\n" . $message);
            $patternKey = 'pattern_' . md5($subject);

            // Check if pattern exists
            $existing = SupportLearningPattern::where('account_id', $accountId)
                ->where('pattern_key', $patternKey)
                ->first();

            if ($existing) {
                $existing->increment('evidence_count');
                $existing->update(['confidence' => min(0.99, $existing->confidence + 0.02)]);
            } else {
                SupportLearningPattern::create([
                    'account_id' => $accountId,
                    'pattern_key' => $patternKey,
                    'category' => (string)($ticket->department ?? 'General'),
                    'problem_signature' => mb_substr($sanitizedProblem, 0, 300),
                    'recommended_solution' => "تم حل المشكلة مسبقاً في التذكرة #{$ticket->id}. مراجعة إعدادات: " . mb_substr($sanitizedProblem, 0, 100),
                    'evidence_count' => 1,
                    'confidence' => 0.85,
                    'status' => 'approved',
                ]);
                $patternsCreated++;
            }
        }

        $run->update([
            'status' => 'completed',
            'processed_count' => $resolvedTickets->count(),
            'summary' => "Run completed. Discovered {$resolvedTickets->count()} tickets, created/updated {$patternsCreated} patterns.",
        ]);

        return [
            'run_id' => $run->id,
            'version' => $run->knowledge_version,
            'discovered_count' => $run->discovered_count,
            'processed_count' => $run->processed_count,
            'patterns_created' => $patternsCreated,
        ];
    }
}
