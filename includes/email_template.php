<?php
/**
 * TutorMind's email layout — one look for every email we send
 * (password reset, study reminders).
 *
 * Email clients are not browsers: layout is tables + inline styles (Outlook
 * uses Word to render), no SVG (Gmail/Outlook drop it — the logo is a PNG,
 * assets/email/logo-mark.png), and web fonts only load in Apple Mail, so every
 * font has a system fallback. Colours are the tm-tokens.css values; the one
 * button is the amber CTA with its solid bottom edge. Clients that honour
 * prefers-color-scheme (Apple Mail, some others) get the dark tokens.
 *
 * All text passed in is escaped here — callers pass plain strings, except the
 * keys ending in _html.
 */

require_once __DIR__ . '/app_url.php';

function emailEsc(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array $o {
 *   preheader:   string  inbox preview line
 *   kicker:      string  small label above the heading ("Password reset")
 *   heading:     string
 *   paragraphs:  string[]
 *   panel:       array|null ['label' => string, 'text' => string]  tinted box (e.g. "Next up")
 *   button:      array ['label' => string, 'url' => string]
 *   after:       string|null  small print under the button
 *   fallback:    bool  show "If the button doesn't work" with the raw link (default true)
 *   footer:      string  why they got this email
 *   unsubscribe: string|null  URL; adds an Unsubscribe link to the footer
 * }
 */
function renderEmail(array $o): string
{
    $base = appBaseUrl();
    $logo = emailEsc($base . '/assets/email/logo-mark.png');
    $home = emailEsc($base);

    $ink = '#1F2937'; $ink2 = '#6b7280'; $line = '#e5e7eb'; $canvas = '#FAF9F6'; $surface = '#ffffff';
    $accent = '#7C3AED'; $tint = '#F5F3FF'; $cta = '#F59E0B'; $ctaEdge = '#D97706';
    $display = "'Funnel Display','Outfit','Segoe UI',Helvetica,Arial,sans-serif";
    $body = "'Outfit','Segoe UI',Helvetica,Arial,sans-serif";

    $paras = '';
    foreach ($o['paragraphs'] ?? [] as $p) {
        $paras .= '<p class="tm-ink" style="margin:0 0 16px;font:400 16px/1.6 ' . $body . ';color:' . $ink . '">' . emailEsc($p) . '</p>';
    }

    $panel = '';
    if (!empty($o['panel'])) {
        $panel = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 24px"><tr>'
            . '<td class="tm-tint" style="background:' . $tint . ';border-radius:10px;padding:14px 16px">'
            . '<p class="tm-ink2" style="margin:0 0 2px;font:500 13px/1.4 ' . $body . ';color:' . $ink2 . '">' . emailEsc($o['panel']['label']) . '</p>'
            . '<p class="tm-ink" style="margin:0;font:600 16px/1.45 ' . $body . ';color:' . $ink . '">' . emailEsc($o['panel']['text']) . '</p>'
            . '</td></tr></table>';
    }

    $btnUrl = emailEsc($o['button']['url']);
    $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px"><tr>'
        . '<td bgcolor="' . $cta . '" style="background:' . $cta . ';border-radius:10px;border-bottom:4px solid ' . $ctaEdge . '">'
        . '<a href="' . $btnUrl . '" style="display:inline-block;padding:14px 24px;font:600 16px/1.1 ' . $body . ';color:' . $ink . ';text-decoration:none;border-radius:10px">'
        . emailEsc($o['button']['label']) . '&nbsp;&rarr;</a></td></tr></table>';

    $after = !empty($o['after'])
        ? '<p class="tm-ink2" style="margin:0 0 12px;font:400 14px/1.55 ' . $body . ';color:' . $ink2 . '">' . emailEsc($o['after']) . '</p>'
        : '';
    $fallback = ($o['fallback'] ?? true)
        ? '<p class="tm-ink2" style="margin:0;font:400 13px/1.55 ' . $body . ';color:' . $ink2 . ';word-break:break-all">If the button doesn&rsquo;t work, paste this into your browser:<br>'
          . '<a href="' . $btnUrl . '" style="color:' . $accent . '">' . $btnUrl . '</a></p>'
        : '';

    $unsub = !empty($o['unsubscribe'])
        ? ' &middot; <a href="' . emailEsc($o['unsubscribe']) . '" style="color:' . $ink2 . ';text-decoration:underline">Unsubscribe</a>'
        : '';

    $kicker = !empty($o['kicker'])
        ? '<p class="tm-accent" style="margin:0 0 8px;font:600 14px/1.3 ' . $display . ';color:' . $accent . '">' . emailEsc($o['kicker']) . '</p>'
        : '';

    $preheader = emailEsc($o['preheader'] ?? '');
    $heading = emailEsc($o['heading']);
    $footer = emailEsc($o['footer'] ?? '');
    $title = $heading;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{$title}</title>
<link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { margin:0; padding:0; }
  a { color:{$accent}; }
  @media (max-width: 600px) {
    .tm-card { padding:24px 20px !important; }
    .tm-h1 { font-size:24px !important; }
  }
  @media (prefers-color-scheme: dark) {
    .tm-canvas { background:#16121F !important; }
    .tm-card { background:#1F1A2E !important; border-color:rgba(255,255,255,0.1) !important; }
    .tm-ink { color:#EDE9F7 !important; }
    .tm-ink2 { color:#9ca3af !important; }
    .tm-accent { color:#A78BFA !important; }
    .tm-tint { background:#2a2440 !important; }
  }
</style>
</head>
<body class="tm-canvas" style="margin:0;padding:0;background:{$canvas}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">{$preheader}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
<table role="presentation" class="tm-canvas" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{$canvas}">
  <tr><td align="center" style="padding:32px 16px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px">
      <tr><td style="padding:0 4px 20px">
        <a href="{$home}" style="text-decoration:none">
          <img src="{$logo}" width="32" height="32" alt="" style="display:inline-block;vertical-align:middle;border:0">
          <span class="tm-ink" style="display:inline-block;vertical-align:middle;margin-left:8px;font:700 20px/1 {$display};letter-spacing:-0.02em;color:{$ink}">TutorMind</span>
        </a>
      </td></tr>
      <tr><td class="tm-card" style="background:{$surface};border:1px solid {$line};border-radius:14px;padding:32px">
        {$kicker}
        <h1 class="tm-ink tm-h1" style="margin:0 0 16px;font:700 26px/1.2 {$display};letter-spacing:-0.02em;color:{$ink}">{$heading}</h1>
        {$paras}
        {$panel}
        {$button}
        {$after}
        {$fallback}
      </td></tr>
      <tr><td style="padding:20px 4px 0">
        <p class="tm-ink2" style="margin:0 0 6px;font:400 13px/1.55 {$body};color:{$ink2}">{$footer}</p>
        <p class="tm-ink2" style="margin:0;font:400 13px/1.55 {$body};color:{$ink2}"><a href="{$home}" style="color:{$ink2};text-decoration:none">TutorMind</a> &middot; your AI study partner{$unsub}</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
