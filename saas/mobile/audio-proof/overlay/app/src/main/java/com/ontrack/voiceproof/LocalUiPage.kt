package com.ontrack.voiceproof

/** An exact HTTPS virtual document, served from verified bytes, never fetched. */
data class LocalUiPage(val nonce:String,val html:String) {
    init { require(nonce.matches(Regex("[a-zA-Z0-9-]{8,64}"))) }
    val url="https://ontrack-ui.invalid/ui/$nonce/index.html"
    fun allows(candidate:String,mainFrame:Boolean,method:String)=mainFrame && method=="GET" && candidate==url
    companion object {
        const val CSP="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; connect-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'"
    }
}
