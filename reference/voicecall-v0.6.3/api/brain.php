<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => false,
  'error' => 'legacy_endpoint_removed',
  'message' => 'v0.4.0 uses Gemini 3.8 Live native audio directly.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
