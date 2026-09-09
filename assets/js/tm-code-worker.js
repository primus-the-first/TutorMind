/**
 * Sandboxed executor for tm-code widgets (assets/js/tm-widgets.js buildCode()).
 * Runs as a Web Worker — no DOM/window/fetch/cookie access — so this is a
 * fundamentally safer isolation boundary than eval()/new Function() on the
 * main thread, regardless of what CSP's 'unsafe-eval' allows there.
 *
 * Shipped as a real static same-origin file (not a blob: URL) because the
 * app's CSP has no `blob:` in script-src and no separate worker-src, so a
 * worker built from a blob URL would be rejected.
 */
self.onmessage = function (e) {
    var lines = [];
    var origLog = console.log;
    console.log = function () {
        lines.push(Array.prototype.slice.call(arguments).map(String).join(' '));
    };
    try {
        // Isolated to this worker's own global scope — no closure over
        // anything outside the worker.
        var fn = new Function(e.data);
        fn();
        postMessage({ ok: true, lines: lines });
    } catch (err) {
        postMessage({ ok: false, lines: lines, error: String((err && err.message) || err) });
    } finally {
        console.log = origLog;
    }
};
