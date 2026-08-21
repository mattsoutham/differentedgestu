<?php
/**
 * Different Edge Studio — Diagnostic Lead Capture
 * Adds subscriber to MailerLite with assessment custom fields.
 * Sends internal alert to DES team via Mailgun.
 * MailerLite automation handles all emails to the lead.
 */

require_once __DIR__ . '/mailgun-config.php';
require_once __DIR__ . '/mailerlite-config.php';

header('Access-Control-Allow-Origin: https://differentedgestudio.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['email'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing fields']);
    exit;
}

$email      = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$first_name = htmlspecialchars($data['first_name'] ?? ($data['name'] ?? ''));
$size       = htmlspecialchars($data['size'] ?? '');
$score      = intval($data['score'] ?? 0);
$band_key   = htmlspecialchars($data['band_key'] ?? '');
$band_name  = htmlspecialchars($data['band_name'] ?? '');
$band_summary        = htmlspecialchars($data['band_summary'] ?? '');
$recommended_product = htmlspecialchars($data['recommended_product'] ?? '');
$signals_raw = $data['signals'] ?? '[]';
$signals_arr = is_array($signals_raw) ? $signals_raw : (json_decode($signals_raw, true) ?? []);
$signals_str = json_encode($signals_arr);
$signal_breakdown_html = build_signal_breakdown_html($signals_arr);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

// 1. Add / update subscriber in MailerLite
$ml_result = mailerlite_upsert($MAILERLITE_API_KEY, $MAILERLITE_GROUP_ID, [
    'email'      => $email,
    'first_name' => $first_name,
    'fields'     => [
        'score'                  => $score,
        'band_key'               => $band_key,
        'band_name'              => $band_name,
        'band_summary'           => $band_summary,
        'recommended_product'    => $recommended_product,
        'company_size'           => $size,
        'signals'                => $signals_str,
        'signal_breakdown_html'  => $signal_breakdown_html,
    ],
]);

// 2. Internal alert to DES team
send_mailgun_email(
    $MAILGUN_API_KEY, $MAILGUN_DOMAIN,
    $NOTIFY_EMAIL,
    "DES Diagnostic <{$FROM_EMAIL}>",
    "New diagnostic lead: {$first_name} — {$score}/100 — {$band_name}",
    build_lead_alert($first_name, $email, $size, $score, $band_name, $recommended_product, $signals_raw, $ml_result)
);

http_response_code(200);
echo json_encode(['ok' => true]);
exit;

// ── Signal breakdown HTML (for Email 1 merge tag) ────────────────────────────

function build_signal_breakdown_html($signals) {
    if (empty($signals)) return '';

    $gaps  = array_filter($signals, fn($s) => empty($s['answer']));
    $wins  = array_filter($signals, fn($s) => !empty($s['answer']));

    $rows = '';
    foreach ($gaps as $s) {
        $label = htmlspecialchars($s['label'] ?? '');
        $rows .= "<tr>
          <td style='padding:9px 16px;font-size:14px;font-weight:600;color:#222222;border-bottom:1px solid #eeeeee;'>{$label}</td>
          <td style='padding:9px 16px;font-size:13px;font-weight:700;color:#222222;text-align:right;white-space:nowrap;border-bottom:1px solid #eeeeee;letter-spacing:0.03em;'>Not yet in place</td>
        </tr>";
    }
    if (!empty($gaps) && !empty($wins)) {
        $rows .= "<tr><td colspan='2' style='padding:0;height:1px;background:#dddddd;'></td></tr>";
    }
    foreach ($wins as $s) {
        $label = htmlspecialchars($s['label'] ?? '');
        $rows .= "<tr>
          <td style='padding:9px 16px;font-size:14px;color:#666666;border-bottom:1px solid #eeeeee;'>{$label}</td>
          <td style='padding:9px 16px;font-size:13px;color:#999999;text-align:right;white-space:nowrap;border-bottom:1px solid #eeeeee;'>In place</td>
        </tr>";
    }

    return "<table width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse;border:1px solid #eeeeee;border-radius:6px;overflow:hidden;'>{$rows}</table>";
}

// ── MailerLite ────────────────────────────────────────────────────────────────

function mailerlite_upsert($api_key, $group_id, $payload) {
    if (!$api_key) return ['error' => 'No MailerLite API key configured'];

    if ($group_id) {
        $payload['groups'] = [$group_id];
    }

    $ch = curl_init('https://connect.mailerlite.com/api/subscribers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$api_key}",
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'curl_error' => $err];
}

// ── Mailgun ───────────────────────────────────────────────────────────────────

function send_mailgun_email($api_key, $domain, $to, $from, $subject, $html) {
    if (!$api_key) return;
    $ch = curl_init("https://api.eu.mailgun.net/v3/{$domain}/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => "api:{$api_key}",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'from'    => $from,
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── Internal alert template ───────────────────────────────────────────────────

function build_lead_alert($name, $email, $size, $score, $band_name, $product, $signals_raw, $ml_result) {
    $band_colour = $score >= 85 ? '#D0EE4D' : ($score >= 50 ? '#C8A800' : ($score >= 26 ? '#E0A852' : '#E05252'));
    $signals     = is_array($signals_raw) ? $signals_raw : json_decode($signals_raw, true) ?? [];

    $sig_rows = '';
    foreach ($signals as $s) {
        $label  = htmlspecialchars($s['label'] ?? '');
        $answer = !empty($s['answer']);
        $status = $answer ? '<span style="color:#6aaa6a;">In place</span>' : '<span style="color:#D0EE4D;font-weight:700;">Gap</span>';
        $sig_rows .= "<tr style='border-top:1px solid #222;'>
          <td style='padding:7px 14px;font-size:13px;color:#ccc;'>{$label}</td>
          <td style='padding:7px 14px;font-size:13px;'>{$status}</td>
        </tr>";
    }

    $ml_status = isset($ml_result['status']) ? intval($ml_result['status']) : 0;
    $ml_badge  = ($ml_status === 200 || $ml_status === 201)
        ? '<span style="color:#6aaa6a;">&#10003; Added to MailerLite</span>'
        : '<span style="color:#E05252;">&#10007; MailerLite error ' . $ml_status . '</span>';

    $safe_email = htmlspecialchars($email);
    $safe_name  = htmlspecialchars($name);

    return "<!DOCTYPE html>
<html lang='en'>
<body style='margin:0;padding:0;background:#0a0a0a;'>
<div style='max-width:540px;margin:0 auto;padding:32px 24px;font-family:Arial,sans-serif;color:#fff;'>
  <h2 style='font-size:18px;font-weight:900;margin:0 0 4px;'>New Diagnostic Submission</h2>
  <p style='color:#666;font-size:12px;margin:0 0 24px;'>{$ml_badge}</p>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
    <tr><td style='padding:10px 14px;color:#888;font-size:13px;width:140px;'>Name</td><td style='padding:10px 14px;font-weight:700;'>{$safe_name}</td></tr>
    <tr style='border-top:1px solid #222;'><td style='padding:10px 14px;color:#888;font-size:13px;'>Email</td><td style='padding:10px 14px;'><a href='mailto:{$safe_email}' style='color:#D0EE4D;'>{$safe_email}</a></td></tr>
    <tr style='border-top:1px solid #222;'><td style='padding:10px 14px;color:#888;font-size:13px;'>Size</td><td style='padding:10px 14px;'>{$size}</td></tr>
    <tr style='border-top:1px solid #222;'><td style='padding:10px 14px;color:#888;font-size:13px;'>Score</td><td style='padding:10px 14px;font-weight:900;font-size:22px;color:{$band_colour};'>{$score}<span style='font-size:13px;color:#555;'>/100</span></td></tr>
    <tr style='border-top:1px solid #222;'><td style='padding:10px 14px;color:#888;font-size:13px;'>Band</td><td style='padding:10px 14px;font-weight:700;color:{$band_colour};'>{$band_name}</td></tr>
    <tr style='border-top:1px solid #222;'><td style='padding:10px 14px;color:#888;font-size:13px;'>Recommended</td><td style='padding:10px 14px;font-weight:700;'>{$product}</td></tr>
  </table>
  <h3 style='font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#666;margin:0 0 8px;'>Signal Breakdown</h3>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:24px;'>
    {$sig_rows}
  </table>
  <a href='mailto:{$safe_email}?subject=Your%20Buyer%20Confidence%20Assessment' style='display:inline-block;background:#D0EE4D;color:#0a0a0a;font-weight:800;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;'>Reply to {$safe_name} &rarr;</a>
</div>
</body>
</html>";
}
