<?php
/**
 * Shared top nav for the signed-in design-system pages (dashboard, group study).
 * Styled by tm-ds.css "App nav"; needs tm-ds.js for the icon sprite + theme toggle.
 *
 *   $appNavCurrent = 'dashboard' | 'group';
 *   include __DIR__ . '/includes/app_nav.php';
 *
 * The Chat link is #appNavChat so a page can point it at the latest conversation
 * (dashboard.js does). The theme button carries data-ds-theme-save, so the choice
 * is also stored on the account — chat reads dark_mode from there.
 */
$appNavCurrent = $appNavCurrent ?? '';
$appNavLinks = [
    'chat'      => ['href' => 'chat',        'long' => 'Chat',          'short' => 'Chat'],
    'dashboard' => ['href' => 'dashboard',   'long' => 'Your learning', 'short' => 'Learning'],
    'group'     => ['href' => 'group_study', 'long' => 'Group study',   'short' => 'Group'],
];
?>
<header class="tm-appnav">
    <nav class="tm-appnav__bar" aria-label="App">
        <a href="chat" class="ds-logo tm-appnav__logo"><img src="assets/logo-bridge.svg" alt=""><span class="tm-appnav__brand">TutorMind</span></a>
        <div class="tm-appnav__links">
            <?php foreach ($appNavLinks as $key => $l): ?>
            <a class="tm-appnav__link" href="<?= $l['href'] ?>"<?= $key === 'chat' ? ' id="appNavChat"' : '' ?><?= $key === $appNavCurrent ? ' aria-current="page"' : '' ?>>
                <span class="tm-appnav__long"><?= $l['long'] ?></span><span class="tm-appnav__short"><?= $l['short'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="ds-icon-btn tm-appnav__theme" type="button" data-ds-theme data-ds-theme-save aria-label="Dark mode"><svg class="ds-i"><use href="#i-moon"/></svg></button>
    </nav>
</header>
