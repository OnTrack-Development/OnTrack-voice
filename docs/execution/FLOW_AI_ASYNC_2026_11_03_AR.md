# Gemini 3.8 Live Flow Builder — asynchronous editor jobs (2026-11-03)

- Uses the existing branch and model \`gemini-3.8-live-extended-thinking\`; no REST model substitution, phone voice route change, WhatsApp change or auto-publish.
- Browser submission returns a scoped opaque job ID immediately. The AI drawer polls authenticated CSRF-protected status and resumes after refresh. A completed tool proposal is validated and saved as a **pending review** only.
- A private database table holds bounded requests and status per account/user/agent; no keys or call recordings stored there. A cancelled job cannot overwrite a new conversation. At most one active job per user+agent.
- PHP-FPM finishes the HTTP response then processes the request; CLI cron provides recovery across worker death. The CLI endpoint is not web-accessible.
- **Recommended host cron:** execute \`/opt/cpanel/ea-php83/root/usr/bin/php /home/appontrack/public_html/saas/console/flow-ai-worker.php\` every minute (confirm actual PHP 8.3 binary in hosting environment; do not install duplicate cron). The worker retries abandoned tasks once after 220 seconds.
- Long Live thinking uses a 140-second absolute WebSocket deadline with bounded frames and partial-read preservation, rather than a 24/27-second synchronous HTTP window. If no structured proposal appears, return failure accurately.
- Offline CI verifies tenant isolation, job persistence, non-duplicate claims, cancellation, and mock Live tool response. Real provider E2E, Apache/FPM background-execution behavior and host cron activation remain unverified until receipt and authenticated browser test.

## 2026-11-03 verified real-provider findings

- GitHub CI release and host receipt succeed for original async queue (`a9b92f5`) and retry build (`fa92fd3`); PHP 8.3, 63 deployed files, backup created.
- Host Cron: one added `flow-ai-worker.php` entry, original three entries preserved; worker claims demo-scoped E2E jobs.
- Live E2E on the configured `gemini-3.8-live-extended-thinking` did **not** produce a structured proposal. Redacted protocol counters: 33 server-content messages, 27 audio chunks, 150 transcription bytes, two turn completions, `IDLE`, **zero tool calls**. This is not a quota, authentication, or WebSocket setup failure.
- A further retry experiment also ended without a verified graph, with a TLS stream read interruption. Client-side TLS idle read is now bounded and tolerant; provider E2E MUST be reverified. No user agent flow or published snapshot was changed; synthetic demo job/chat rows removed.
- Public bug reports show the same model sometimes speaks without emitting required NON_BLOCKING tool calls. A bounded same-session re-prompt is implemented; it is a mitigation, not a guarantee. Do not auto-fallback to another model or mark editor proposals as successful without validated tool output.
- Broad standalone WHMCS/WhatsApp workflow currently fails in its existing unrelated bridge fixture; the dedicated console publish workflow passes. WhatsApp is not part of this repair.
