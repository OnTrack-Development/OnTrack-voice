<?php

namespace App\Services\Support;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketDraft;
use App\Models\Support\SupportSpecialistAgent;
use App\Services\Billing\FeatureGateService;
use Illuminate\Support\Facades\Log;

final class MultiAgentOrchestrator
{
    private FeatureGateService $featureGate;

    public function __construct(?FeatureGateService $featureGate = null)
    {
        $this->featureGate = $featureGate ?? new FeatureGateService();
    }

    /**
     * Ingests and processes an incoming support ticket through the multi-agent pipeline.
     *
     * @param array $payload Ticket data: ['account_id' => int, 'external_ticket_id' => string, 'subject' => string, 'message' => string, 'client_id' => string, 'attachments' => array, 'evidence' => array]
     * @return array Result with ticket, draft, consultations, and review verdict
     */
    public function process(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 1);
        $subject = trim((string)($payload['subject'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $externalId = (string)($payload['external_ticket_id'] ?? '');

        // 1. Create or retrieve SupportTicket
        $ticket = SupportTicket::firstOrCreate(
            [
                'account_id' => $accountId,
                'external_ticket_id' => $externalId !== '' ? $externalId : null,
                'source' => (string)($payload['source'] ?? 'whmcs'),
            ],
            [
                'client_id' => (string)($payload['client_id'] ?? ''),
                'client_name' => (string)($payload['client_name'] ?? 'العميل'),
                'client_email' => (string)($payload['client_email'] ?? ''),
                'client_phone' => (string)($payload['client_phone'] ?? ''),
                'subject' => $subject,
                'department' => (string)($payload['department'] ?? 'الدعم الفني'),
                'priority' => (string)($payload['priority'] ?? 'medium'),
                'status' => 'processing',
                'latest_message' => $message,
                'metadata' => (array)($payload['metadata'] ?? []),
            ]
        );

        $ticket->update(['latest_message' => $message, 'status' => 'processing']);

        // 2. Vision Extraction (AttachmentVision)
        $images = (array)($payload['image_paths'] ?? []);
        $visionEvidence = [];
        if (!empty($images)) {
            $apiKey = (string)config('services.gemini.key', env('GEMINI_API_KEY', ''));
            $visionEvidence = AttachmentVision::extractEvidence($images, $apiKey);
        }

        // 3. Multi-Agent Planning & Consultations
        $maxConsultations = 3;
        // Feature gate check for max consultations allowed
        try {
            $account = \App\Models\Account::find($accountId);
            if ($account) {
                $maxConsultations = $this->featureGate->limitFor($account, 'multi_agent_consultations', 3);
            }
        } catch (\Throwable $e) {}

        $consultationPlan = UnifiedSupportKernel::planConsultations($subject, $message, $maxConsultations);
        $evidenceBundle = (array)($payload['evidence'] ?? []);
        if (!empty($visionEvidence['domains'])) {
            $evidenceBundle['vision_domains'] = $visionEvidence['domains'];
        }

        $consultations = [];
        if (!empty($consultationPlan)) {
            $consultations = UnifiedAdaptiveConsultation::consult($ticket, $consultationPlan, $evidenceBundle);
        }

        // 4. Synthesize Proposed Reply
        $draftText = $this->synthesizeReply($ticket, $consultations, $visionEvidence);

        // 5. Mandatory Reviewer Gate (ReviewerVerdict)
        $catalogItems = (array)($evidenceBundle['catalog'] ?? []);
        $review = ReviewerVerdict::evaluate($draftText, [
            'catalog_items' => $catalogItems,
            'client_name' => $ticket->client_name,
            'is_public' => true,
        ]);

        // 6. Save Draft
        $draft = SupportTicketDraft::create([
            'ticket_id' => $ticket->id,
            'account_id' => $accountId,
            'draft_text' => $review['cleaned_text'],
            'reviewer_verdict' => $review['verdict'],
            'reviewer_feedback' => implode(' | ', $review['reasons']),
            'evidence_summary' => !empty($visionEvidence['summary']) ? $visionEvidence['summary'] : null,
            'confidence' => $review['confidence'],
            'is_published' => false,
        ]);

        $ticket->update(['status' => 'waiting_approval']);

        return [
            'ticket_id' => $ticket->id,
            'external_ticket_id' => $ticket->external_ticket_id,
            'status' => $ticket->status,
            'draft_id' => $draft->id,
            'draft_text' => $draft->draft_text,
            'reviewer_verdict' => $draft->reviewer_verdict,
            'reviewer_feedback' => $draft->reviewer_feedback,
            'confidence' => (float)$draft->confidence,
            'consultations' => $consultations,
            'vision_evidence' => $visionEvidence,
        ];
    }

    private function synthesizeReply(SupportTicket $ticket, array $consultations, array $visionEvidence): string
    {
        $parts = [];
        $parts[] = "أهلاً بك أستاذ {$ticket->client_name}،";

        if (!empty($visionEvidence['summary']) && $visionEvidence['summary'] !== 'No images provided.') {
            $parts[] = "تم الاطلاع على المرفق وتشخيص التالي: " . $visionEvidence['summary'];
        }

        if (!empty($consultations)) {
            $adviceItems = [];
            foreach ($consultations as $c) {
                $adviceItems[] = $c['response'];
            }
            $parts[] = implode("\n", $adviceItems);
        } else {
            $parts[] = "شكراً لتواصلك معنا بخصوص [{$ticket->subject}]. تم مراجعة الطلب وجارٍ تقديم الدعم اللازم.";
        }

        $parts[] = "في حال وجود أي استفسار إضافي، يسعدنا تواصلك دائماً.";
        $parts[] = "مع تحيات فريق OnTrack الذكي";

        return implode("\n\n", $parts);
    }
}
