/**
 * CSRF protection for same-origin writes (finding V-02).
 *
 * The server rejects every non-GET request that lacks the session's token, sent as
 * the X-CSRF-Token header (or a csrf_token form field). Rather than touch every
 * fetch() call site, this wraps window.fetch: for a same-origin request that is not
 * GET/HEAD/OPTIONS it waits for the token (fetched once, then cached) and adds the
 * header. If the server still answers 403 "security token", the token is refetched
 * and the request is retried once (a stale tab after the session changed).
 *
 * Load this FIRST, as a plain (non-defer, non-async) script, so the wrapper exists
 * before any other app script makes a request.
 *
 * window.TmCsrf.token() -> Promise<string> for code that needs the raw token (e.g. to
 * fill a hidden csrf_token input on a native form).
 */
(function () {
  'use strict';

  if (window.TmCsrf || typeof window.fetch !== 'function') return;

  var nativeFetch = window.fetch.bind(window);
  var HEADER = 'X-CSRF-Token';
  var SAFE = { GET: 1, HEAD: 1, OPTIONS: 1 };
  var TOKEN_ERROR = /security token|csrf/i;

  // The app root is wherever this script's own URL says it is (…/assets/js/tm-csrf.js),
  // which holds on prod ('') and XAMPP ('/TutorMind') alike, and from rewritten URLs
  // such as /chat/12 where a bare relative path would resolve to the wrong place.
  var tokenUrl = 'includes/csrf.php?action=get_token';
  try {
    var src = document.currentScript && document.currentScript.src;
    if (src) tokenUrl = new URL('../../includes/csrf.php?action=get_token', src).href;
  } catch (e) { /* keep the relative fallback */ }

  var cached = null;
  var inflight = null;

  /** Fetch the token from the server. force=true ignores the cache (and any older request). */
  function load(force) {
    if (!force) {
      if (cached) return Promise.resolve(cached);
      if (inflight) return inflight;
    }
    var p = nativeFetch(tokenUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('token request failed (' + r.status + ')');
        return r.json();
      })
      .then(function (j) {
        if (!j || !j.token) throw new Error('no token in response');
        if (inflight === p) { cached = j.token; inflight = null; }
        return j.token;
      })
      .catch(function (err) {
        // Nothing cached, so the next request tries again
        if (inflight === p) { cached = null; inflight = null; }
        throw err;
      });
    inflight = p;
    return p;
  }

  function isRequest(x) { return typeof Request !== 'undefined' && x instanceof Request; }

  function methodOf(input, init) {
    var m = (init && init.method) || (isRequest(input) ? input.method : 'GET');
    return String(m).toUpperCase();
  }

  function sameOrigin(input) {
    try {
      var u = isRequest(input) ? input.url : String(input);
      return new URL(u, location.href).origin === location.origin;
    } catch (e) {
      return false;
    }
  }

  function isStream(body) {
    return typeof ReadableStream !== 'undefined' && body instanceof ReadableStream;
  }

  /** One attempt: same arguments as the caller's, plus the token header. */
  function attempt(input, init, token) {
    var base = init || {};
    var headers = new Headers(base.headers !== undefined ? base.headers : (isRequest(input) ? input.headers : undefined));
    if (token) headers.set(HEADER, token); // FormData bodies: Content-Type is left to the browser
    var nextInit = Object.assign({}, base, { headers: headers });
    // A Request keeps its own url/method/body; the init overrides only what we pass
    return nativeFetch(input, nextInit);
  }

  function tokenError(res) {
    if (res.status !== 403) return Promise.resolve(false);
    return res.clone().json().then(function (j) {
      return !!(j && typeof j.error === 'string' && TOKEN_ERROR.test(j.error));
    }, function () { return false; });
  }

  function guardedFetch(input, init) {
    var method, same;
    try {
      method = methodOf(input, init);
      same = sameOrigin(input);
    } catch (e) {
      return nativeFetch(input, init);
    }
    if (SAFE[method] || !same) return nativeFetch(input, init);

    // Keep an untouched copy for the retry: a Request's body is consumed by sending it
    var spare = null;
    try { spare = isRequest(input) ? input.clone() : null; } catch (e) { spare = null; }
    var canRetry = !(init && isStream(init.body)) && (!isRequest(input) || spare !== null);

    return load(false).catch(function () { return ''; }).then(function (token) {
      return attempt(input, init, token).then(function (res) {
        if (!canRetry) return res;
        return tokenError(res).then(function (stale) {
          if (!stale) return res;
          return load(true).then(function (fresh) {
            return attempt(spare || input, init, fresh);
          }, function () { return res; });
        });
      });
    });
  }

  window.fetch = guardedFetch;
  window.TmCsrf = {
    token: function () { return load(false); }
  };
})();
