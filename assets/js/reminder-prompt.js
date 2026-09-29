/**
 * Asks, once, in the flow of a real study session, whether the student wants
 * reminders to come back. Onboarding used to ask before they'd seen any value
 * (and never actually created a push subscription); this asks after a few
 * replies, when the answer is informed.
 *
 * Saves through the same paths as Settings: subscribeToPush() (push-notifications.js)
 * for this device, api/user_settings.php for notifications_enabled / email_reminders.
 * Email stays a separate, explicit choice — never bundled into the push button.
 *
 * tutor_mysql.js calls TmReminderPrompt.onReply(container) after each AI reply.
 */
(function () {
  const REPLIES_BEFORE_ASK = 3;                 // an actual exchange, not one stray message
  const SNOOZE_MS = 30 * 24 * 60 * 60 * 1000;   // "Not now" comes back in a month
  const SNOOZE_KEY = 'tm_remind_snoozed_at';

  let replies = 0;
  let shown = false;

  function snoozed() {
    try {
      const at = parseInt(localStorage.getItem(SNOOZE_KEY) || '0', 10);
      return at && Date.now() - at < SNOOZE_MS;
    } catch (e) { return false; }
  }

  function snooze() {
    try { localStorage.setItem(SNOOZE_KEY, String(Date.now())); } catch (e) { /* private window: asks again next visit */ }
  }

  function canPush() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
      && Notification.permission !== 'denied'
      && !!(window.TutorMindPushConfig && window.TutorMindPushConfig.vapidPublicKey)
      && typeof subscribeToPush === 'function';
  }

  async function saveSettings(settings) {
    const response = await fetch('api/user_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings)
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'Could not save that.');
  }

  function build() {
    const el = document.createElement('aside');
    el.className = 'tm-remind';
    el.setAttribute('aria-labelledby', 'tm-remind-title');
    el.innerHTML = `
      <h3 class="tm-remind__title" id="tm-remind-title">Want a nudge to come back?</h3>
      <p class="tm-remind__text">Short sessions spread over a few days stick far better than one long one. We can remind you every few days.</p>
      <p class="tm-remind__note" role="status" aria-live="polite" hidden></p>
      <div class="tm-remind__actions">
        ${canPush() ? '<button type="button" class="tm-dialog-btn tm-dialog-btn-confirm" data-choice="push">Remind me on this device</button>' : ''}
        <button type="button" class="tm-dialog-btn ${canPush() ? 'tm-dialog-btn-cancel' : 'tm-dialog-btn-confirm'}" data-choice="email">Email me</button>
        <button type="button" class="tm-remind__skip" data-choice="skip">Not now</button>
      </div>`;
    return el;
  }

  function wire(el) {
    const note = el.querySelector('.tm-remind__note');
    const buttons = el.querySelectorAll('button');

    const say = (msg) => { note.textContent = msg; note.hidden = false; };
    const busy = (on) => buttons.forEach(b => { b.disabled = on; });

    el.addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-choice]');
      if (!btn) return;
      const choice = btn.dataset.choice;

      if (choice === 'skip') {
        snooze();
        el.remove();
        return;
      }

      busy(true);
      note.hidden = true;
      try {
        if (choice === 'push') {
          await subscribeToPush();               // asks the browser for permission; must run from this click
          await saveSettings({ notifications_enabled: true });
        } else {
          await saveSettings({ notifications_enabled: true, email_reminders: true });
        }
        window.TutorMindUser.remindersOn = true;
        el.classList.add('is-done');
        el.innerHTML = '<p class="tm-remind__text"><strong>Done.</strong> We\'ll nudge you every few days. You can change this any time in Settings.</p>';
        setTimeout(() => el.remove(), 6000);
      } catch (err) {
        console.error('Reminder opt-in failed:', err);
        say(choice === 'push' && Notification.permission === 'denied'
          ? 'Notifications are blocked for this site in your browser. Email works instead.'
          : 'That didn\'t work. You can try again, or set it up later in Settings.');
        busy(false);
      }
    });
  }

  window.TmReminderPrompt = {
    onReply(container) {
      replies++;
      if (shown || replies < REPLIES_BEFORE_ASK || !container) return;
      if (window.TutorMindUser && window.TutorMindUser.remindersOn) return;
      if (snoozed()) return;

      shown = true;
      const el = build();
      wire(el);
      container.appendChild(el);
    }
  };
})();
