<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    public function test_public_marketing_pages_are_reachable_and_arabic(): void
    {
        $pages = [
            '/', '/platform', '/features/agent-builder', '/features/smart-buttons',
            '/features/knowledge', '/features/integrations', '/features/channels', '/features/telegram',
            '/features/inbox', '/features/operations', '/pricing', '/security',
            '/enterprise', '/about', '/contact', '/developers', '/docs',
            '/industries/ecommerce', '/industries/real-estate', '/industries/hospitality',
            '/industries/education', '/industries/healthcare', '/industries/logistics',
            '/industries/marketing-agencies', '/industries/hosting', '/industries/professional-services',
        ];
        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertSee('OnTrack');
        }
    }

    public function test_sitemap_is_valid_and_covers_pages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('sitemaps.org', $response->getContent());
        $this->assertStringContainsString('/features/agent-builder', $response->getContent());
        $this->assertStringContainsString('/pricing', $response->getContent());
    }

    public function test_unknown_page_returns_not_found(): void
    {
        $this->get('/no-such-page-xyz')->assertNotFound();
    }
}