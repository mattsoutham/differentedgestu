<?php
/**
 * Different Edge Studio — Complete System Application Capture
 */

$NOTIFY_EMAIL    = 'matt@differentedgestudio.com, dan@differentedgestudio.com';
$FROM_EMAIL      = 'results@mg.differentedgestudio.com';
$FROM_NAME       = 'Different Edge Studio';
$MAILGUN_API_KEY = '';  // same key as lead-capture.php
$MAILGUN_DOMAIN  = 'mg.differentedgestudio.com';

header('Access-Control-Allow-Origin: https://differentedgestudio.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['name']) || empty($data['email'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing fields']);
    exit;
}

$name       = htmlspecialchars($data['name']     ?? '');
$company    = htmlspecialchars($data['company']  ?? '');
$email      = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$revenue    = htmlspecialchars($data['revenue']  ?? '');
$commitment = htmlspecialchars($data['commitment'] ?? '');
$track      = htmlspecialchars($data['track']    ?? '');
$metric     = htmlspecialchars($data['metric']   ?? '');
$qualified  = (bool)($data['qualified'] ?? false);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid email']);
    exit;
}

$status_label = $qualified ? '✅ QUALIFIED' : '❌ NOT QUALIFIED';
$subject      = "Complete System Application: {$name} ({$company}) — {$status_label}";

send_email(
    $MAILGUN_API_KEY, $MAILGUN_DOMAIN,
    $NOTIFY_EMAIL,
    "DES Applications <{$FROM_EMAIL}>",
    $subject,
    build_team_email($name, $company, $email, $revenue, $commitment, $track, $metric, $qualified)
);

http_response_code(200);
echo json_encode(['ok'=>true]);
exit;

function send_email($api_key, $domain, $to, $from, $subject, $html) {
    if ($api_key) {
        $ch = curl_init("https://api.eu.mailgun.net/v3/{$domain}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "api:{$api_key}",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['from'=>$from,'to'=>$to,'subject'=>$subject,'html'=>$html],
        ]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$from}\r\n";
        mail($to, $subject, $html, $headers);
    }
}

function build_team_email($name, $company, $email, $revenue, $commitment, $track, $metric, $qualified) {
    $badge  = $qualified
        ? "<span style='background:#c8f000;color:#0a0a0a;font-weight:900;padding:4px 12px;border-radius:4px;font-size:13px;'>QUALIFIED</span>"
        : "<span style='background:#E05252;color:#fff;font-weight:900;padding:4px 12px;border-radius:4px;font-size:13px;'>NOT QUALIFIED</span>";

    $disqual = [];
    if ($revenue === 'Under £500K')         $disqual[] = 'Revenue under £500K';
    if ($commitment === 'Not sure')          $disqual[] = 'Commitment: Not sure';
    if ($track === 'No')                     $disqual[] = 'Track record: No';
    $reason = $qualified ? '—' : implode(', ', $disqual);

    return "<!DOCTYPE html>
<html lang='en'>
<body style='margin:0;padding:0;background:#0a0a0a;'>
<div style='max-width:520px;margin:0 auto;padding:32px 24px;font-family:Arial,sans-serif;color:#fff;'>
  <img src='https://differentedgestudio.com/images/Logo-light.svg' alt='Different Edge Studio' style='height:28px;margin-bottom:28px;' />
  <div style='display:flex;align-items:center;gap:12px;margin-bottom:24px;'>
    <h2 style='font-size:20px;font-weight:900;margin:0;'>New Complete System Application</h2>
    {$badge}
  </div>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
    <tr><td style='padding:10px 16px;color:#888;font-size:13px;width:140px;'>Name</td><td style='padding:10px 16px;font-weight:700;'>" . htmlspecialchars($name) . "</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Company</td><td style='padding:10px 16px;'>" . htmlspecialchars($company) . "</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Email</td><td style='padding:10px 16px;'><a href='mailto:" . htmlspecialchars($email) . "' style='color:#c8f000;text-decoration:none;'>" . htmlspecialchars($email) . "</a></td></tr>
  </table>
  <h3 style='font-size:12px;text-transform:uppercase;letter-spacing:0.1em;color:#888;margin:0 0 8px;'>Answers</h3>
  <table style='width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
    <tr><td style='padding:10px 16px;color:#888;font-size:13px;width:140px;'>Revenue</td><td style='padding:10px 16px;'>" . htmlspecialchars($revenue) . "</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Commitment</td><td style='padding:10px 16px;'>" . htmlspecialchars($commitment) . "</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Track record</td><td style='padding:10px 16px;'>" . htmlspecialchars($track) . "</td></tr>
    <tr style='border-top:1px solid #1a1a1a;'><td style='padding:10px 16px;color:#888;font-size:13px;'>Target metric</td><td style='padding:10px 16px;font-style:italic;color:#ddd;'>" . htmlspecialchars($metric) . "</td></tr>
  </table>
  " . (!$qualified ? "<p style='background:#1a0000;border:1px solid #E05252;border-radius:6px;padding:12px 16px;font-size:13px;color:#E05252;margin:0 0 20px;'><strong>Disqualified because:</strong> {$reason}</p>" : "") . "
  <a href='mailto:" . htmlspecialchars($email) . "?subject=Your Complete System Application' style='display:inline-block;background:#c8f000;color:#0a0a0a;font-weight:800;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;'>Reply to " . htmlspecialchars($name) . " &rarr;</a>
</div>
</body>
</html>";
}
