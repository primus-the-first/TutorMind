/**
 * Dashboard → chat hand-off for "Worth another look".
 *
 * The dashboard stores { conversationId, prompt } in sessionStorage under
 * tm_review_prompt and opens /chat/{conversationId}. Here we prefill the
 * composer with that prompt — never auto-send — so the learner asks for
 * another go themselves. Load after tutor_mysql.js: its DOMContentLoaded
 * handler attaches the composer's 'input' listeners (auto-resize, send pill)
 * synchronously, so dispatching 'input' below updates them.
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
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
});
