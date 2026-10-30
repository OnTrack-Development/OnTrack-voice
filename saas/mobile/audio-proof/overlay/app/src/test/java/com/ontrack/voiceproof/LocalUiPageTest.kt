package com.ontrack.voiceproof
import org.junit.Assert.*
import org.junit.Test
class LocalUiPageTest {
    @Test fun onlyExactCurrentMainGetCanReadVerifiedHtml(){
        val page=LocalUiPage("render-nonce-1234","<html>verified</html>")
        assertTrue(page.allows(page.url,true,"GET"))
        for(url in listOf("data:text/html;base64,abcd","https://example.com/",page.url+"?x=1",page.url.replace("render-nonce-1234","stale-nonce-4567")))assertFalse(page.allows(url,true,"GET"))
        assertFalse(page.allows(page.url,false,"GET"));assertFalse(page.allows(page.url,true,"POST"))
    }
}
