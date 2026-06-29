<?php
/**
 * Different Edge Studio — Diagnostic Lead Capture
 * Drop this file in the root of the site (same folder as index.html).
 * Uses Mailgun REST API via curl for reliable delivery.
 * Falls back to PHP mail() if Mailgun key is not set.
 */

// ── CONFIG ────────────────────────────────────────────────────────────────────
$NOTIFY_EMAIL      = 'matt@differentedgestudio.com, dan@differentedgestudio.com';
$FROM_EMAIL        = 'results@mg.differentedgestudio.com';
$FROM_NAME         = 'Different Edge Studio';
$MAILGUN_API_KEY   = '';  // paste your Mailgun private API key here (bbbc8336-...)
$MAILGUN_DOMAIN    = 'mg.differentedgestudio.com';
// ─────────────────────────────────────────────────────────────────────────────

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
file_put_contents(__DIR__ . '/lead-debug.log', date('Y-m-d H:i:s') . " | " . $_SERVER['REQUEST_METHOD'] . " | " . $raw . "\n", FILE_APPEND);
$data = json_decode($raw, true);

if (!$data || empty($data['name']) || empty($data['email'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing fields']);
    exit;
}

$name           = htmlspecialchars($data['name']);
$email          = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$size           = htmlspecialchars($data['size'] ?? '');
$score          = intval($data['score'] ?? 0);
$recommendation = htmlspecialchars($data['recommendation'] ?? '');
$areas          = $data['areas'] ?? [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

// 1. Email results to the user
send_email(
    $MAILGUN_API_KEY, $MAILGUN_DOMAIN,
    $email,
    "{$FROM_NAME} <{$FROM_EMAIL}>",
    'Your Sales Video Assessment Results',
    build_user_email($name, $score, $recommendation, $areas)
);

// 2. Alert the DES team
send_email(
    $MAILGUN_API_KEY, $MAILGUN_DOMAIN,
    $NOTIFY_EMAIL,
    "DES Diagnostic <{$FROM_EMAIL}>",
    "New diagnostic lead: {$name} — {$score}/7 — {$recommendation}",
    build_lead_email($name, $email, $size, $score, $recommendation, $areas)
);

http_response_code(200);
echo json_encode(['ok' => true]);
exit;

// ── Mailer ────────────────────────────────────────────────────────────────────

function send_email($api_key, $domain, $to, $from, $subject, $html) {
    if ($api_key) {
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
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        file_put_contents(__DIR__ . '/lead-debug.log', date('Y-m-d H:i:s') . " | Mailgun {$status}: {$result} | curl: {$err}\n", FILE_APPEND);
    } else {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$from}\r\n";
        mail($to, $subject, $html, $headers);
    }
}

// ── Email templates ───────────────────────────────────────────────────────────

function build_user_email($name, $score, $recommendation, $areas) {
    $pct = round(($score / 7) * 100);
    $bar = max(4, $pct);

    $reco_map = [
        'Sales Engine'       => 'Your pipeline has the biggest gap in how video supports your sales process. The Sales Engine is where to start.',
        'Visibility'         => 'Your market presence and authority are the weakest link. Visibility will compound the fastest for you.',
        'Showcase'           => 'Your core brand video and showcase assets are holding you back. Getting Showcase right anchors everything else.',
        'The Complete System'=> 'Your business has gaps across all three areas. The Complete System is the most efficient way to close them all.',
    ];
    $reco_body = $reco_map[$recommendation] ?? "Book a call and we'll walk you through exactly where to start.";

    $area_rows = '';
    foreach ($areas as $a) {
        $label  = htmlspecialchars($a['label'] ?? '');
        $yes    = intval($a['yes'] ?? 0);
        $total  = intval($a['total'] ?? 0);
        $a_pct  = $total > 0 ? round(($yes / $total) * 100) : 0;
        $colour = $a_pct >= 67 ? '#c8f000' : ($a_pct >= 34 ? '#E0A852' : '#E05252');
        $area_rows .= "
        <tr>
          <td style='padding:10px 16px;color:#aaa;font-size:14px;width:140px;'>{$label}</td>
          <td style='padding:10px 16px;'>
            <div style='background:#1a1a1a;border-radius:4px;height:6px;width:100%;margin-bottom:4px;'>
              <div style='background:{$colour};height:6px;border-radius:4px;width:{$a_pct}%;'></div>
            </div>
            <span style='color:{$colour};font-size:12px;font-weight:700;'>{$yes}/{$total} answered yes</span>
          </td>
        </tr>";
    }

    return "<!DOCTYPE html>
<html lang='en'>
<body style='margin:0;padding:0;background:#0a0a0a;'>
<div style='max-width:600px;margin:0 auto;padding:40px 24px;font-family:Inter,Arial,sans-serif;color:#fff;'>
  <img src='https://differentedgestudio.com/images/Logo-light.svg' alt='Different Edge Studio' style='height:32px;margin-bottom:40px;' />
  <h1 style='font-size:26px;font-weight:900;margin:0 0 8px;'>Hi {$name},</h1>
  <p style='color:#888;font-size:15px;margin:0 0 32px;'>Here are your Sales Video Assessment results.</p>
  <div style='background:#111111;border:2px solid #c8f000;border-radius:10px;padding:28px;text-align:center;margin-bottom:32px;'>
    <p style='color:#888;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;margin:0 0 12px;'>Overall Score</p>
    <p style='font-size:72px;font-weight:900;color:#c8f000;margin:0;line-height:1;'>{$score}<span style='font-size:32px;color:#444;'>/7</span></p>
    <div style='background:#1a1a1a;border-radius:6px;height:8px;width:80%;margin:20px auto 0;'>
      <div style='background:#c8f000;height:8px;border-radius:6px;width:{$bar}%;'></div>
    </div>
  </div>
  <h2 style='font-size:16px;font-weight:700;margin:0 0 4px;'>Area breakdown</h2>
  <p style='color:#666;font-size:13px;margin:0 0 16px;'>Where your biggest gaps are right now.</p>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:32px;'>
    {$area_rows}
  </table>
  <div style='background:#111;border-left:3px solid #c8f000;border-radius:0 8px 8px 0;padding:20px 24px;margin-bottom:32px;'>
    <p style='font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#888;margin:0 0 6px;'>Our recommendation</p>
    <h3 style='font-size:18px;font-weight:800;color:#fff;margin:0 0 10px;'>{$recommendation}</h3>
    <p style='color:#aaa;font-size:14px;line-height:1.6;margin:0;'>{$reco_body}</p>
  </div>
  <p style='color:#888;font-size:14px;margin:0 0 20px;'>This assessment covers 7 signals. Our full audit covers 30 — including your site, your competitors, and your sales sequence. It's free and takes 20 minutes.</p>
  <a href='https://calendly.com/dan_de/bootcamp-application' style='display:inline-block;background:#c8f000;color:#0a0a0a;font-weight:800;text-decoration:none;padding:14px 28px;border-radius:6px;font-size:15px;'>Book your free audit call &rarr;</a>
  <hr style='border:none;border-top:1px solid #1a1a1a;margin:40px 0 20px;' />
  <p style='color:#444;font-size:12px;margin:0;'>Different Edge Studio &bull; <a href='https://differentedgestudio.com' style='color:#666;text-decoration:none;'>differentedgestudio.com</a></p>
</div>
</body>
</html>";
}

function build_lead_email($name, $email, $size, $score, $recommendation, $areas) {
    $urgency = $score <= 2
        ? '🔴 High intent — very few yes answers, significant pain'
        : ($score <= 4 ? '🟡 Mid intent — clear gaps, good fit' : '🟢 Warm lead — mostly yes, already using some video');

    $area_rows = '';
    foreach ($areas as $a) {
        $label = htmlspecialchars($a['label'] ?? '');
        $yes   = intval($a['yes'] ?? 0);
        $total = intval($a['total'] ?? 0);
        $a_pct = $total > 0 ? round(($yes / $total) * 100) : 0;
        $area_rows .= "<tr style='border-top:1px solid #1a1a1a;'>
          <td style='padding:8px 16px;color:#888;font-size:13px;'>{$label}</td>
          <td style='padding:8px 16px;font-weight:700;color:#c8f000;font-size:13px;'>{$yes}/{$total} ({$a_pct}%)</td>
        </tr>";
    }

    $reply_email = htmlspecialchars($email);
    $reply_name  = htmlspecialchars($name);

    return "<!DOCTYPE html>
<html lang='en'>
<body style='margin:0;padding:0;background:#0a0a0a;'>
<div style='max-width:520px;margin:0 auto;padding:32px 24px;font-family:Arial,sans-serif;color:#fff;'>
  <h2 style='font-size:20px;font-weight:900;margin:0 0 4px;'>New Diagnostic Lead</h2>
  <p style='color:#888;font-size:13px;margin:0 0 24px;'>{$urgency}</p>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:24px;'>
    <tr><td style='padding:10px 16px;color:#888;font-size:13px;width:130px;'>Name</td><td style='padding:10px 16px;font-weight:700;'>{$reply_name}</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Email</td><td style='padding:10px 16px;'><a href='mailto:{$reply_email}' style='color:#c8f000;text-decoration:none;'>{$reply_email}</a></td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Business size</td><td style='padding:10px 16px;'>{$size}</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Score</td><td style='padding:10px 16px;font-weight:900;font-size:20px;color:#c8f000;'>{$score}<span style='font-size:14px;color:#444;'>/7</span></td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Recommendation</td><td style='padding:10px 16px;font-weight:700;'>{$recommendation}</td></tr>
  </table>
  <h3 style='font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#888;margin:0 0 8px;'>Area Breakdown</h3>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:24px;'>
    {$area_rows}
  </table>
  <a href='mailto:{$reply_email}?subject=Your Different Edge Studio Assessment' style='display:inline-block;background:#c8f000;color:#0a0a0a;font-weight:800;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;'>Reply to {$reply_name} &rarr;</a>
</div>
</body>
</html>";
}
