/**
 * Dashboard → chat hand-off for "Worth another look".
 *
 * The dashboard stores { conversationId, prompt } in sessionStorage under
 * tm_review_prompt and opens /chat/{conversationId}. Here we prefill the
 * composer with that prompt — never auto-send — so the learner asks for
 * another go themselves.
 *
 * tutor_mysql.js attaches the composer's 'input' listeners (auto-resize, send
 * pill) only after `await settingsManager.loadSettings()`, so one 'input' now
 * can land before anyone listens. Re-fire it until the send pill shows the
 * text (or the learner edits, or ~10s pass).
 */
document.addEventListener('DOMContentLoaded', () => {
    let handoff;
    try {
        handoff = JSON.parse(sessionStorage.getItem('tm_review_prompt') || 'null');
        sessionStorage.removeItem('tm_review_prompt');
    } catch (e) { return; /* storage blocked — chat just opens as usual */ }
    if (!handoff || !handoff.prompt) return;

    // Only prefill the chat it was meant for (null = a fresh chat).
    const current = document.getElementById('conversation_id')?.value || null;
    if (String(handoff.conversationId ?? '') !== String(current ?? '')) return;

    const input = document.getElementById('question');
    if (!input) return;
    input.value = handoff.prompt;
    const row = document.getElementById('inputPillRow');
    const settle = () => {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    };
    settle();
    let tries = 0;
    const timer = setInterval(() => {
        const done = !row || row.classList.contains('has-text');
        if (done || input.value !== handoff.prompt || ++tries > 40) { clearInterval(timer); return; }
        settle();
    }, 250);
});
