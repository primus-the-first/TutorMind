/**
 * TutorMind Design System — tm-ds.js  (preview, 2026-09)
 * Behaviour for design-system.html + landing-ds.html (styles: tm-ds.css).
 *   - Monoline icon sprite (<svg class="ds-i"><use href="#i-mic"/></svg>)
 *   - Theme toggle (body.dark-mode + 'tutormind-theme', same as index.html)
 *   - Mobile menu, ask-bar chips, scroll reveal
 *   - 3D: pointer tilt on [data-tilt], CSS subject objects, and a WebGL
 *     bridge arch hero ([data-ds-bridge], needs three.js) that "draws in"
 *     like the Bridge Draw loader in tm-loader.css.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---------- Icon sprite ---------- */
  var ICONS = {
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    send: '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4 18-7z"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    close: '<path d="M6 6l12 12M18 6 6 18"/>',
    camera: '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
    upload: '<path d="M12 15V4M7 9l5-5 5 5M5 15v4h14v-4"/>',
    keyboard: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h.01M11 10h.01M15 10h.01M7 14h10"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    moon: '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
    check: '<path d="M4 13l5 5L20 6"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'eye-off': '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.9 8.3 2 12 2 12s3.5 7 10 7c1.8 0 3.4-.5 4.8-1.3M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    alert: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>'
  };

  function injectSprite() {
    var symbols = Object.keys(ICONS).map(function (name) {
      return '<symbol id="i-' + name + '" viewBox="0 0 24 24">' + ICONS[name] + '</symbol>';
    }).join('');
    var holder = document.createElement('div');
    holder.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">' + symbols + '</svg>';
    document.body.insertBefore(holder.firstChild, document.body.firstChild);
  }

  /* ---------- Theme ---------- */
  function initTheme() {
    var buttons = document.querySelectorAll('[data-ds-theme]');
    function sync() {
      var dark = document.body.classList.contains('dark-mode');
      buttons.forEach(function (btn) {
        btn.setAttribute('aria-pressed', String(dark));
        var use = btn.querySelector('use');
        if (use) use.setAttribute('href', dark ? '#i-sun' : '#i-moon');
      });
    }
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dark = document.body.classList.toggle('dark-mode');
        document.body.classList.toggle('light-mode', !dark);
        try { localStorage.setItem('tutormind-theme', dark ? 'dark' : 'light'); } catch (e) {}
        sync();
      });
    });
    sync();
  }

  /* ---------- Mobile menu ---------- */
  function initMenu() {
    document.querySelectorAll('[data-ds-menu-btn]').forEach(function (btn) {
      var menu = document.getElementById(btn.getAttribute('aria-controls'));
      if (!menu) return;
      var use = btn.querySelector('use');
      function set(open) {
        btn.setAttribute('aria-expanded', String(open));
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        menu.hidden = !open;
        if (use) use.setAttribute('href', open ? '#i-close' : '#i-menu');
      }
      btn.addEventListener('click', function () { set(menu.hidden); });
      menu.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { set(false); btn.focus(); } });
    });
  }

  /* ---------- Ask bar ---------- */
  function initAsk() {
    document.querySelectorAll('[data-ds-ask]').forEach(function (form) {
      var input = form.querySelector('input');
      var scope = form.parentElement;
      scope.querySelectorAll('[data-fill]').forEach(function (chip) {
        chip.addEventListener('click', function () {
          scope.querySelectorAll('[data-fill]').forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
          chip.setAttribute('aria-pressed', 'true');
          input.value = chip.getAttribute('data-fill');
          input.focus();
        });
      });
      form.addEventListener('submit', function (e) {
        if (!input.value.trim()) { e.preventDefault(); input.focus(); }
      });
    });
  }

  /* ---------- Generated 3D object parts ---------- */
  function buildObjects() {
    document.querySelectorAll('.ds-wave').forEach(function (el) {
      var html = '';
      for (var r = 0; r < 5; r++) for (var c = 0; c < 5; c++) html += '<span style="--r:' + r + ';--c:' + c + '"></span>';
      el.innerHTML = html;
    });
    document.querySelectorAll('.ds-helix').forEach(function (el) {
      var html = '';
      for (var i = 0; i < 10; i++) html += '<span style="--i:' + i + '"></span>';
      el.innerHTML = html;
    });
    document.querySelectorAll('.ds-wavebars').forEach(function (el) {
      var n = parseInt(el.getAttribute('data-count'), 10) || 14, html = '';
      for (var i = 0; i < n; i++) html += '<i style="--i:' + i + '"></i>';
      el.innerHTML = html;
    });
  }

  /* ---------- Pointer tilt ---------- */
  function initTilt() {
    if (!finePointer || reduceMotion) return;
    // [data-tilt] tilts the element itself; [data-tilt-vars] only feeds
    // --rx/--ry so a child (e.g. .ds-constellation__plane) can use them.
    document.querySelectorAll('[data-tilt], [data-tilt-vars]').forEach(function (el) {
      var max = parseFloat(el.getAttribute('data-tilt') || el.getAttribute('data-tilt-vars')) || 8;
      if (el.hasAttribute('data-tilt')) el.classList.add('ds-tilt');
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5;
        var py = (e.clientY - r.top) / r.height - 0.5;
        el.classList.add('is-tilting');
        el.style.setProperty('--ry', (px * max * 2).toFixed(2) + 'deg');
        el.style.setProperty('--rx', (-py * max * 2).toFixed(2) + 'deg');
      });
      el.addEventListener('pointerleave', function () {
        el.classList.remove('is-tilting');
        el.style.setProperty('--rx', '0deg');
        el.style.setProperty('--ry', '0deg');
      });
    });
  }

  /* ---------- Scroll: reveal once, run 3D objects only while visible ---------- */
  function initScroll() {
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.ds-reveal, .ds-step, .ds-art').forEach(function (el) { el.classList.add('is-live'); });
      return;
    }
    var once = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-live'); once.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -12% 0px' });
    document.querySelectorAll('.ds-reveal, .ds-step').forEach(function (el) { once.observe(el); });

    var live = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { entry.target.classList.toggle('is-live', entry.isIntersecting); });
    });
    document.querySelectorAll('.ds-art, [data-ds-live]').forEach(function (el) { live.observe(el); });
  }

  /* ---------- WebGL bridge arch hero ---------- */
  function initBridge(host) {
    var THREE = window.THREE;
    if (!THREE) return;

    var renderer;
    try {
      renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    } catch (e) {
      return; // no WebGL: the static logo fallback stays visible
    }
    host.classList.add('has-webgl');
    host.appendChild(renderer.domElement);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    // Colours come from the CSS tokens so tm-ds.css stays the source of truth
    var css = getComputedStyle(document.body);
    function token(name, fallback) { return (css.getPropertyValue(name) || '').trim() || fallback; }
    var C = {
      primary: new THREE.Color(token('--primary', '#7C3AED')),
      light: new THREE.Color(token('--primary-light', '#A78BFA')),
      dark: new THREE.Color(token('--primary-dark', '#5B21B6')),
      cta: new THREE.Color(token('--cta', '#F59E0B'))
    };

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(34, 1, 0.1, 500);
    camera.position.set(0, 16, 78);
    camera.lookAt(0, 9, 0);

    scene.add(new THREE.HemisphereLight(0xffffff, C.dark, 1.3));
    var sun = new THREE.DirectionalLight(0xffffff, 2.4);
    sun.position.set(18, 42, 30);
    sun.castShadow = true;
    sun.shadow.mapSize.set(1024, 1024);
    sun.shadow.camera.left = -30; sun.shadow.camera.right = 30;
    sun.shadow.camera.top = 30; sun.shadow.camera.bottom = -30;
    sun.shadow.radius = 6;
    scene.add(sun);

    var group = new THREE.Group();
    scene.add(group);

    // Same curve as assets/logo-bridge.svg: M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30
    // (x - 20, 30 - y) so the arch stands on y = 0.
    var V = function (x, y) { return new THREE.Vector3(x, y, 0); };
    var path = new THREE.CurvePath();
    path.add(new THREE.CubicBezierCurve3(V(-14, 0), V(-14, 10), V(-6, 18), V(0, 18)));
    path.add(new THREE.CubicBezierCurve3(V(0, 18), V(6, 18), V(14, 10), V(14, 0)));
    var RADIAL = 32;
    var archGeo = new THREE.TubeGeometry(path, 160, 1.9, RADIAL, false);
    var arch = new THREE.Mesh(archGeo, new THREE.MeshStandardMaterial({ color: C.primary, roughness: 0.32, metalness: 0.08 }));
    arch.castShadow = true;
    group.add(arch);
    var totalIdx = archGeo.index.count;
    var ringIdx = RADIAL * 6;

    function ball(r, color, x, y, emissive) {
      var m = new THREE.Mesh(
        new THREE.SphereGeometry(r, 40, 24),
        new THREE.MeshStandardMaterial({ color: color, roughness: 0.3, emissive: emissive || 0x000000, emissiveIntensity: emissive ? 0.35 : 0 })
      );
      m.position.set(x, y, 0);
      m.castShadow = true;
      group.add(m);
      return m;
    }
    var baseL = ball(2.6, C.dark, -14, 0);
    var baseR = ball(2.6, C.light, 14, 0);
    var top = ball(3.1, C.cta, 0, 23.5, C.cta);

    // Floating study "blocks" around the arch
    var floaters = [
      { geo: new THREE.BoxGeometry(3.2, 3.2, 3.2), color: C.light, pos: [-17, 22, -6] },
      { geo: new THREE.OctahedronGeometry(2.3), color: C.primary, pos: [17, 14, -8] },
      { geo: new THREE.TorusGeometry(2, 0.7, 16, 40), color: C.light, pos: [16, 4, 8] },
      { geo: new THREE.IcosahedronGeometry(1.8), color: C.dark, pos: [-18, 9, 7] }
    ].map(function (f, i) {
      var m = new THREE.Mesh(f.geo, new THREE.MeshStandardMaterial({ color: f.color, roughness: 0.4 }));
      m.position.set(f.pos[0], f.pos[1], f.pos[2]);
      m.castShadow = true;
      m.userData = { y: f.pos[1], phase: i * 1.7 };
      group.add(m);
      return m;
    });

    var ground = new THREE.Mesh(new THREE.PlaneGeometry(240, 240), new THREE.ShadowMaterial({ opacity: 0.14 }));
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -2.7;
    ground.receiveShadow = true;
    scene.add(ground);

    function resize() {
      var w = host.clientWidth, h = host.clientHeight;
      if (!w || !h) return;
      renderer.setSize(w, h, false);
      camera.aspect = w / h;
      // keep the whole arch in frame on narrow boxes
      camera.position.z = w / h < 0.9 ? 78 / (w / h) * 0.9 : 78;
      camera.updateProjectionMatrix();
    }
    resize();
    if ('ResizeObserver' in window) new ResizeObserver(resize).observe(host);
    else window.addEventListener('resize', resize);

    var pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    host.addEventListener('pointermove', function (e) {
      var r = host.getBoundingClientRect();
      pointer.tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      pointer.ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
    });
    host.addEventListener('pointerleave', function () { pointer.tx = 0; pointer.ty = 0; });

    function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
    function easeOutBack(t) { var c = 1.9; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); }

    var DRAW = 1.6, POP = 0.5;
    function frame(t) {
      // Bridge Draw: stroke the arch on, left base first, then pop the top dot
      var d = Math.min(t / DRAW, 1);
      var idx = Math.floor((totalIdx * easeInOut(d)) / ringIdx) * ringIdx;
      archGeo.setDrawRange(0, idx);
      baseR.scale.setScalar(d >= 0.98 ? 1 : 0.001);
      var p = Math.min(Math.max((t - DRAW) / POP, 0), 1);
      top.scale.setScalar(Math.max(easeOutBack(p), 0.001));
      top.position.y = 23.5 + Math.sin(t * 2) * 0.5 * p;

      floaters.forEach(function (m) {
        m.rotation.x = t * 0.5 + m.userData.phase;
        m.rotation.y = t * 0.7;
        m.position.y = m.userData.y + Math.sin(t * 1.1 + m.userData.phase) * 1.2;
        m.scale.setScalar(Math.max(Math.min((t - 0.4 - m.userData.phase * 0.12) / 0.6, 1), 0.001));
      });

      pointer.x += (pointer.tx - pointer.x) * 0.06;
      pointer.y += (pointer.ty - pointer.y) * 0.06;
      group.rotation.y = Math.sin(t * 0.35) * 0.3 + pointer.x * 0.45;
      group.rotation.x = pointer.y * 0.12;
      renderer.render(scene, camera);
    }

    if (reduceMotion) {
      frame(DRAW + POP + 1); // one static, fully drawn frame
      return;
    }

    var running = false, start = null, elapsed = 0, inView = true;
    function loop(now) {
      if (!running) return;
      if (start === null) start = now - elapsed * 1000;
      elapsed = (now - start) / 1000;
      frame(elapsed);
      requestAnimationFrame(loop);
    }
    function play() { if (!running && inView && !document.hidden) { running = true; start = null; requestAnimationFrame(loop); } }
    function pause() { running = false; }
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        inView = entries[0].isIntersecting;
        inView ? play() : pause();
      }).observe(host);
    } else {
      play();
    }
    document.addEventListener('visibilitychange', function () { document.hidden ? pause() : play(); });
  }

  /* ---------- Boot ---------- */
  function boot() {
    document.documentElement.classList.remove('no-js');
    injectSprite();
    buildObjects();
    initTheme();
    initMenu();
    initAsk();
    initTilt();
    initScroll();
    document.querySelectorAll('[data-ds-bridge]').forEach(initBridge);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
