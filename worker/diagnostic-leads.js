/**
 * Different Edge Studio — Diagnostic Lead Worker
 * Fill in your values in the CONFIG block below, then hit Deploy.
 */

const CONFIG = {
  MAILGUN_API_KEY:  'FILL_IN',   // your Mailgun API key
  MAILGUN_DOMAIN:   'FILL_IN',   // e.g. mg.differentedgestudio.com
  FROM_EMAIL:       'results@differentedgestudio.com',
  NOTIFY_EMAIL:     'matt@mjsmedia.co.uk',  // add ,dan@... if needed
  MAILERLITE_API_KEY:  '',  // leave blank if not using
  MAILERLITE_GROUP_ID: '',  // leave blank if not using
};

const ALLOWED_ORIGIN = 'https://differentedgestudio.com';

export default {
  async fetch(request, env) {
    const origin = request.headers.get('Origin') || '';
    const corsHeaders = {
      'Access-Control-Allow-Origin': origin.includes('differentedgestudio.com') ? origin : ALLOWED_ORIGIN,
      'Access-Control-Allow-Methods': 'POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    };

    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders });
    }

    if (request.method !== 'POST') {
      return new Response('Method not allowed', { status: 405 });
    }

    let data;
    try {
      data = await request.json();
    } catch {
      return new Response(JSON.stringify({ ok: false, error: 'Invalid JSON' }), {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    const { name, email, size, score, recommendation, areas } = data;

    if (!name || !email || !score === undefined) {
      return new Response(JSON.stringify({ ok: false, error: 'Missing fields' }), {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    try {
      // 1. Email results to the user
      await sendEmail(null, {
        to: `${name} <${email}>`,
        from: `Different Edge Studio <${CONFIG.FROM_EMAIL}>`,
        subject: 'Your Sales Video Assessment Results',
        html: buildUserEmail(name, score, recommendation, areas),
      });

      // 2. Alert the DES team
      await sendEmail(null, {
        to: CONFIG.NOTIFY_EMAIL,
        from: `DES Diagnostic <${CONFIG.FROM_EMAIL}>`,
        subject: `New diagnostic lead: ${name} — ${score}/7 — ${recommendation}`,
        html: buildLeadEmail(name, email, size, score, recommendation, areas),
      });

      // 3. Add to MailerLite (optional — only runs if MAILERLITE_API_KEY is set)
      if (CONFIG.MAILERLITE_API_KEY) {
        await addToMailerLite(null, { name, email });
      }

      return new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    } catch (err) {
      console.error(err);
      return new Response(JSON.stringify({ ok: false, error: err.message }), {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }
  },
};

/* ── Mailgun ─────────────────────────────────────────────────── */

async function sendEmail(_, { to, from, subject, html }) {
  const body = new URLSearchParams({ to, from, subject, html });
  const res = await fetch(`https://api.mailgun.net/v3/${CONFIG.MAILGUN_DOMAIN}/messages`, {
    method: 'POST',
    headers: {
      Authorization: `Basic ${btoa('api:' + CONFIG.MAILGUN_API_KEY)}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Mailgun: ${res.status} ${text}`);
  }
}

/* ── MailerLite ──────────────────────────────────────────────── */

async function addToMailerLite(_, { name, email }) {
  const [firstName, ...rest] = name.trim().split(' ');
  const payload = {
    email,
    fields: { name: firstName, last_name: rest.join(' ') || '' },
    status: 'active',
  };
  if (CONFIG.MAILERLITE_GROUP_ID) {
    payload.groups = [CONFIG.MAILERLITE_GROUP_ID];
  }
  const res = await fetch('https://connect.mailerlite.com/api/subscribers', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${CONFIG.MAILERLITE_API_KEY}`,
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  });
  if (!res.ok && res.status !== 409) { // 409 = already subscribed, that's fine
    const text = await res.text();
    console.warn(`MailerLite: ${res.status} ${text}`);
  }
}

/* ── Email templates ─────────────────────────────────────────── */

function buildUserEmail(name, score, recommendation, areas) {
  const pct = Math.round((score / 7) * 100);
  const bar = Math.max(4, pct);

  const areaRows = Object.values(areas).map(a => {
    const aPct = a.total > 0 ? Math.round((a.yes / a.total) * 100) : 0;
    const colour = aPct >= 67 ? '#c8f000' : aPct >= 34 ? '#E0A852' : '#E05252';
    return `
      <tr>
        <td style="padding:10px 16px;color:#aaa;font-size:14px;width:140px;">${a.label}</td>
        <td style="padding:10px 16px;">
          <div style="background:#1a1a1a;border-radius:4px;height:6px;width:100%;margin-bottom:4px;">
            <div style="background:${colour};height:6px;border-radius:4px;width:${aPct}%;"></div>
          </div>
          <span style="color:${colour};font-size:12px;font-weight:700;">${a.yes}/${a.total} answered yes</span>
        </td>
      </tr>`;
  }).join('');

  const recoMap = {
    'Sales Engine': 'Your pipeline has the biggest gap in how video supports your sales process. The Sales Engine is where to start.',
    'Visibility': 'Your market presence and authority are the weakest link. Visibility will compound the fastest for you.',
    'Showcase': 'Your core brand video and showcase assets are holding you back. Getting Showcase right anchors everything else.',
    'The Complete System': 'Your business has gaps across all three areas. The Complete System is the most efficient way to close them all.',
  };
  const recoBody = recoMap[recommendation] || 'Book a call and we\'ll walk you through exactly where to start.';

  return `<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#0a0a0a;">
<div style="max-width:600px;margin:0 auto;padding:40px 24px;font-family:Inter,Arial,sans-serif;color:#fff;">

  <img src="https://differentedgestudio.com/images/Logo-light.svg" alt="Different Edge Studio" style="height:32px;margin-bottom:40px;" />

  <h1 style="font-size:26px;font-weight:900;margin:0 0 8px;">Hi ${name},</h1>
  <p style="color:#888;font-size:15px;margin:0 0 32px;">Here are your Sales Video Assessment results.</p>

  <div style="background:#111111;border:2px solid #c8f000;border-radius:10px;padding:28px;text-align:center;margin-bottom:32px;">
    <p style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;margin:0 0 12px;">Overall Score</p>
    <p style="font-size:72px;font-weight:900;color:#c8f000;margin:0;line-height:1;">${score}<span style="font-size:32px;color:#444;">/7</span></p>
    <div style="background:#1a1a1a;border-radius:6px;height:8px;width:80%;margin:20px auto 0;">
      <div style="background:#c8f000;height:8px;border-radius:6px;width:${bar}%;"></div>
    </div>
  </div>

  <h2 style="font-size:16px;font-weight:700;margin:0 0 4px;">Area breakdown</h2>
  <p style="color:#666;font-size:13px;margin:0 0 16px;">Where your biggest gaps are right now.</p>
  <table style="width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:32px;">
    ${areaRows}
  </table>

  <div style="background:#111;border-left:3px solid #c8f000;border-radius:0 8px 8px 0;padding:20px 24px;margin-bottom:32px;">
    <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#888;margin:0 0 6px;">Our recommendation</p>
    <h3 style="font-size:18px;font-weight:800;color:#fff;margin:0 0 10px;">${recommendation}</h3>
    <p style="color:#aaa;font-size:14px;line-height:1.6;margin:0;">${recoBody}</p>
  </div>

  <p style="color:#888;font-size:14px;margin:0 0 20px;">This assessment covers 7 signals. Our full audit covers 30 — including your site, your competitors, and your sales sequence. It's free and takes 20 minutes.</p>

  <a href="https://calendly.com/dan_de/bootcamp-application"
     style="display:inline-block;background:#c8f000;color:#0a0a0a;font-weight:800;text-decoration:none;padding:14px 28px;border-radius:6px;font-size:15px;letter-spacing:-0.01em;">
    Book your free audit call &rarr;
  </a>

  <hr style="border:none;border-top:1px solid #1a1a1a;margin:40px 0 20px;" />
  <p style="color:#444;font-size:12px;margin:0;">Different Edge Studio &bull; <a href="https://differentedgestudio.com" style="color:#666;text-decoration:none;">differentedgestudio.com</a></p>
</div>
</body>
</html>`;
}

function buildLeadEmail(name, email, size, score, recommendation, areas) {
  const areaRows = Object.values(areas).map(a => {
    const aPct = a.total > 0 ? Math.round((a.yes / a.total) * 100) : 0;
    return `<tr style="border-top:1px solid #1a1a1a;">
      <td style="padding:8px 16px;color:#888;font-size:13px;">${a.label}</td>
      <td style="padding:8px 16px;font-weight:700;color:#c8f000;font-size:13px;">${a.yes}/${a.total} (${aPct}%)</td>
    </tr>`;
  }).join('');

  const urgency = score <= 2 ? '🔴 High intent — very few yes answers, significant pain' :
                  score <= 4 ? '🟡 Mid intent — clear gaps, good fit' :
                               '🟢 Warm lead — mostly yes, already using some video';

  return `<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#0a0a0a;">
<div style="max-width:520px;margin:0 auto;padding:32px 24px;font-family:Arial,sans-serif;color:#fff;">

  <h2 style="font-size:20px;font-weight:900;margin:0 0 4px;">New Diagnostic Lead</h2>
  <p style="color:#888;font-size:13px;margin:0 0 24px;">${urgency}</p>

  <table style="width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:24px;">
    <tr><td style="padding:10px 16px;color:#888;font-size:13px;width:130px;">Name</td><td style="padding:10px 16px;font-weight:700;">${name}</td></tr>
    <tr style="border-top:1px solid #1a1a1a;"><td style="padding:10px 16px;color:#888;font-size:13px;">Email</td><td style="padding:10px 16px;"><a href="mailto:${email}" style="color:#c8f000;text-decoration:none;">${email}</a></td></tr>
    <tr style="border-top:1px solid #1a1a1a;"><td style="padding:10px 16px;color:#888;font-size:13px;">Business size</td><td style="padding:10px 16px;">${size}</td></tr>
    <tr style="border-top:1px solid #1a1a1a;"><td style="padding:10px 16px;color:#888;font-size:13px;">Score</td><td style="padding:10px 16px;font-weight:900;font-size:20px;color:#c8f000;">${score}<span style="font-size:14px;color:#444;">/7</span></td></tr>
    <tr style="border-top:1px solid #1a1a1a;"><td style="padding:10px 16px;color:#888;font-size:13px;">Recommendation</td><td style="padding:10px 16px;font-weight:700;">${recommendation}</td></tr>
  </table>

  <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#888;margin:0 0 8px;">Area Breakdown</h3>
  <table style="width:100%;border-collapse:collapse;background:#111;border-radius:8px;overflow:hidden;margin-bottom:24px;">
    ${areaRows}
  </table>

  <a href="mailto:${email}?subject=Your Different Edge Studio Assessment"
     style="display:inline-block;background:#c8f000;color:#0a0a0a;font-weight:800;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;">
    Reply to ${name} &rarr;
  </a>

</div>
</body>
</html>`;
}
