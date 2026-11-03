# Gemini 3.8 Live Flow Builder — asynchronous editor jobs (2026-11-03)

- Uses the existing branch and model \`gemini-3.8-live-extended-thinking\`; no REST model substitution, phone voice route change, WhatsApp change or auto-publish.
- Browser submission returns a scoped opaque job ID immediately. The AI drawer polls authenticated CSRF-protected status and resumes after refresh. A completed tool proposal is validated and saved as a **pending review** only.
- A private database table holds bounded requests and status per account/user/agent; no keys or call recordings stored there. A cancelled job cannot overwrite a new conversation. At most one active job per user+agent.
- PHP-FPM finishes the HTTP response then processes the request; CLI cron provides recovery across worker death. The CLI endpoint is not web-accessible.
- **Recommended host cron:** execute \`/opt/cpanel/ea-php83/root/usr/bin/php /home/appontrack/public_html/saas/console/flow-ai-worker.php\` every minute (confirm actual PHP 8.3 binary in hosting environment; do not install duplicate cron). The worker retries abandoned tasks once after 220 seconds.
- Long Live thinking uses a 140-second absolute WebSocket deadline with bounded frames and partial-read preservation, rather than a 24/27-second synchronous HTTP window. If no structured proposal appears, return failure accurately.
- Offline CI verifies tenant isolation, job persistence, non-duplicate claims, cancellation, and mock Live tool response. Real provider E2E, Apache/FPM background-execution behavior and host cron activation remain unverified until receipt and authenticated browser test.
