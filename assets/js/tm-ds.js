/**
 * TutorMind Design System — tm-ds.js  (preview, 2026-09)
 * Behaviour for design-system.html + landing-ds.html (styles: tm-ds.css).
 *   - Monoline icon sprite (<svg class="ds-i"><use href="#i-mic"/></svg>)
 *   - Theme toggle (body.dark-mode + 'tutormind-theme', same as index.html)
 *   - Mobile menu, ask-bar chips, scroll reveal
 *   - 3D: pointer tilt on [data-tilt], CSS subject objects, and WebGL
 *     scenes (need three.js): the landing bridge that "draws in" like the
 *     Bridge Draw loader, the login padlock and the register keystone arch.
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
    alert: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>',
    // group study
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
    link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    volume: '<path d="M4 9v6h4l5 4V5L8 9z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
    stop: '<rect x="6" y="6" width="12" height="12" rx="2"/>',
    leave: '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 16l-4-4 4-4M6 12h10"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>'
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

  /* =========================================================
     WebGL scenes (three.js)
       [data-ds-bridge]            landing hero — Bridge Draw in 3D
       [data-ds-scene="lock"]      login — the arch is a padlock shackle;
                                   typing brings the key, success unlocks it
       [data-ds-scene="keystone"]  register — each valid field lays a pair
                                   of stones; the amber keystone goes in last
     Interactive scenes expose host.tmScene = { setProgress(0..1),
     attempt(), deny(), grant() } for the page's form code.
     ========================================================= */

  // Shared renderer / camera / lights / resize / pointer / pause-offscreen
  function createStage(host, opts) {
    var THREE = window.THREE;
    if (!THREE) return null;
    var renderer;
    try {
      renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    } catch (e) {
      return null; // no WebGL: the static fallback stays visible
    }
    host.classList.add('has-webgl');
    host.appendChild(renderer.domElement);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, opts.maxDpr || 2));
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
    var look = new THREE.Vector3().fromArray(opts.lookAt);
    var camBase = new THREE.Vector3().fromArray(opts.camera);
    camera.position.copy(camBase);
    camera.lookAt(look);

    scene.add(new THREE.HemisphereLight(0xffffff, C.dark, 1.3));
    var sun = new THREE.DirectionalLight(0xffffff, 2.4);
    sun.position.set(18, 46, 30);
    sun.castShadow = true;
    sun.shadow.mapSize.set(1024, 1024);
    sun.shadow.camera.left = -34; sun.shadow.camera.right = 34;
    sun.shadow.camera.top = 40; sun.shadow.camera.bottom = -34;
    sun.shadow.radius = 6;
    scene.add(sun);

    var group = new THREE.Group();
    scene.add(group);

    var ground = new THREE.Mesh(new THREE.PlaneGeometry(260, 260), new THREE.ShadowMaterial({ opacity: 0.16 }));
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = opts.groundY;
    ground.receiveShadow = true;
    scene.add(ground);

    function resize() {
      var w = host.clientWidth, h = host.clientHeight;
      if (!w || !h) return;
      renderer.setSize(w, h, false);
      camera.aspect = w / h;
      // pull back on narrow boxes so the whole model stays in frame
      var k = camera.aspect < 0.9 ? 0.9 / camera.aspect : 1;
      camera.position.copy(camBase).sub(look).multiplyScalar(k).add(look);
      camera.lookAt(look);
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

    var themeFns = [];
    new MutationObserver(function () {
      var dark = document.body.classList.contains('dark-mode');
      themeFns.forEach(function (fn) { fn(dark); });
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    var stage = {
      THREE: THREE, C: C, scene: scene, camera: camera, group: group, pointer: pointer,
      reduced: reduceMotion,
      mat: function (color, extra) {
        var o = { color: color, roughness: 0.35, metalness: 0.08 };
        for (var k in extra) o[k] = extra[k];
        return new THREE.MeshStandardMaterial(o);
      },
      mesh: function (geo, material, parent) {
        var m = new THREE.Mesh(geo, material);
        m.castShadow = true;
        (parent || group).add(m);
        return m;
      },
      // exponential approach; snaps under reduced motion
      approach: function (cur, target, dt, speed) {
        return reduceMotion ? target : cur + (target - cur) * (1 - Math.exp(-dt * speed));
      },
      renderOnce: function (frame, t) { frame(t, 0); renderer.render(scene, camera); },
      // fn(isDark) now and on every theme toggle, for materials that sit on
      // the (theme-following) stage colour rather than a brand colour
      onTheme: function (fn) {
        themeFns.push(fn);
        fn(document.body.classList.contains('dark-mode'));
      },
      run: function (frame) {
        // opts.maxFps throttles idle scenes (auth pages) so software-rendered
        // WebGL on GPU-less machines doesn't starve the page's own JS
        var running = false, last = null, t = 0, inView = true, gap = opts.maxFps ? 1000 / opts.maxFps - 2 : 0;
        function loop(now) {
          if (!running) return;
          if (gap && last !== null && now - last < gap) { requestAnimationFrame(loop); return; }
          var dt = last === null ? 0 : Math.min((now - last) / 1000, 0.05);
          last = now;
          t += dt;
          pointer.x += (pointer.tx - pointer.x) * 0.06;
          pointer.y += (pointer.ty - pointer.y) * 0.06;
          frame(t, dt);
          renderer.render(scene, camera);
          requestAnimationFrame(loop);
        }
        function play() { if (!running && inView && !document.hidden) { running = true; last = null; requestAnimationFrame(loop); } }
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
    };
    return stage;
  }

  // The logo's arch: M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30,
  // re-centred as x ∈ [-14, 14], y ∈ [0, 18]. map(x, y) lets a scene rescale it.
  function logoArch(THREE, map) {
    map = map || function (x, y) { return new THREE.Vector3(x, y, 0); };
    var path = new THREE.CurvePath();
    path.add(new THREE.CubicBezierCurve3(map(-14, 0), map(-14, 10), map(-6, 18), map(0, 18)));
    path.add(new THREE.CubicBezierCurve3(map(0, 18), map(6, 18), map(14, 10), map(14, 0)));
    return path;
  }

  function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function easeOutBack(t) { var c = 1.9; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); }
  function clamp01(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }

  /* ---------- Landing hero: the bridge draws itself in ---------- */
  function initBridge(host) {
    var s = createStage(host, { camera: [0, 16, 78], lookAt: [0, 9, 0], groundY: -2.7 });
    if (!s) return;
    var THREE = s.THREE, C = s.C;

    var RADIAL = 32;
    var archGeo = new THREE.TubeGeometry(logoArch(THREE), 160, 1.9, RADIAL, false);
    s.mesh(archGeo, s.mat(C.primary, { roughness: 0.32 }));
    var totalIdx = archGeo.index.count, ringIdx = RADIAL * 6;

    function ball(r, color, x, y, glow) {
      var m = s.mesh(new THREE.SphereGeometry(r, 40, 24), s.mat(color, { roughness: 0.3, emissive: glow ? color : 0x000000, emissiveIntensity: glow ? 0.35 : 0 }));
      m.position.set(x, y, 0);
      return m;
    }
    ball(2.6, C.dark, -14, 0);
    var baseR = ball(2.6, C.light, 14, 0);
    var top = ball(3.1, C.cta, 0, 23.5, true);

    // Floating study "blocks" around the arch
    var floaters = [
      { geo: new THREE.BoxGeometry(3.2, 3.2, 3.2), color: C.light, pos: [-17, 22, -6] },
      { geo: new THREE.OctahedronGeometry(2.3), color: C.primary, pos: [17, 14, -8] },
      { geo: new THREE.TorusGeometry(2, 0.7, 16, 40), color: C.light, pos: [16, 4, 8] },
      { geo: new THREE.IcosahedronGeometry(1.8), color: C.dark, pos: [-18, 9, 7] }
    ].map(function (f, i) {
      var m = s.mesh(f.geo, s.mat(f.color, { roughness: 0.4 }));
      m.position.fromArray(f.pos);
      m.userData = { y: f.pos[1], phase: i * 1.7 };
      return m;
    });

    var DRAW = 1.6, POP = 0.5;
    function frame(t) {
      // Bridge Draw: stroke the arch on, left base first, then pop the top dot
      var d = Math.min(t / DRAW, 1);
      archGeo.setDrawRange(0, Math.floor((totalIdx * easeInOut(d)) / ringIdx) * ringIdx);
      baseR.scale.setScalar(d >= 0.98 ? 1 : 0.001);
      var p = clamp01((t - DRAW) / POP);
      top.scale.setScalar(Math.max(easeOutBack(p), 0.001));
      top.position.y = 23.5 + Math.sin(t * 2) * 0.5 * p;

      floaters.forEach(function (m) {
        m.rotation.x = t * 0.5 + m.userData.phase;
        m.rotation.y = t * 0.7;
        m.position.y = m.userData.y + Math.sin(t * 1.1 + m.userData.phase) * 1.2;
        m.scale.setScalar(Math.max(Math.min((t - 0.4 - m.userData.phase * 0.12) / 0.6, 1), 0.001));
      });

      s.group.rotation.y = Math.sin(t * 0.35) * 0.3 + s.pointer.x * 0.45;
      s.group.rotation.x = s.pointer.y * 0.12;
    }

    if (s.reduced) { s.renderOnce(frame, DRAW + POP + 1); return; }
    s.run(frame);
  }

  /* ---------- Login: padlock whose shackle is the bridge arch ---------- */
  function initLock(host) {
    var s = createStage(host, { camera: [32, 26, 74], lookAt: [0, 14, 0], groundY: -4, maxDpr: 1.5, maxFps: 30 });
    if (!s) return;
    var THREE = s.THREE, C = s.C, V = function (x, y, z) { return new THREE.Vector3(x, y, z || 0); };
    var lock = new THREE.Group();
    s.group.add(lock);

    // Body: a rounded slab with a keyhole cut all the way through
    var W = 26, H = 18, R = 4, D = 7, BOTTOM = -4, TOP = BOTTOM + H, BEVEL = 1;
    var body = new THREE.Shape();
    body.moveTo(-W / 2 + R, BOTTOM);
    body.lineTo(W / 2 - R, BOTTOM);
    body.quadraticCurveTo(W / 2, BOTTOM, W / 2, BOTTOM + R);
    body.lineTo(W / 2, TOP - R);
    body.quadraticCurveTo(W / 2, TOP, W / 2 - R, TOP);
    body.lineTo(-W / 2 + R, TOP);
    body.quadraticCurveTo(-W / 2, TOP, -W / 2, TOP - R);
    body.lineTo(-W / 2, BOTTOM + R);
    body.quadraticCurveTo(-W / 2, BOTTOM, -W / 2 + R, BOTTOM);
    var KY = BOTTOM + 11, KR = 2.3, SLOT = 1.2, dy = Math.sqrt(KR * KR - SLOT * SLOT);
    var keyhole = new THREE.Path();
    keyhole.moveTo(-SLOT, KY - 6);
    keyhole.lineTo(-SLOT, KY - dy);
    keyhole.absarc(0, KY, KR, Math.atan2(-dy, -SLOT), Math.atan2(-dy, SLOT), true);
    keyhole.lineTo(SLOT, KY - 6);
    keyhole.lineTo(-SLOT, KY - 6);
    body.holes.push(keyhole);
    var bodyGeo = new THREE.ExtrudeGeometry(body, { depth: D, bevelEnabled: true, bevelThickness: BEVEL, bevelSize: 0.8, bevelSegments: 3, curveSegments: 14 });
    bodyGeo.translate(0, 0, -D / 2);
    var bodyMesh = s.mesh(bodyGeo, s.mat(C.primary, { roughness: 0.3 }), lock);
    bodyMesh.receiveShadow = true;
    var FRONT = D / 2 + BEVEL;

    // Amber light inside the keyhole (the logo's amber dot, relocated)
    var glowMat = s.mat(C.cta, { emissive: C.cta, emissiveIntensity: 0.9 });
    var glow = new THREE.Mesh(new THREE.BoxGeometry(5.6, 9.5, 0.6), glowMat);
    glow.position.set(0, KY - 2, 0);
    lock.add(glow);

    // Shackle = the logo arch, narrowed to the body, with legs seated in it.
    // It hangs off a pivot on the left leg so it can lift and swing open.
    var LEG = 8, SEAT = 3.5, RISE = 2.5, y0 = TOP - SEAT, y1 = TOP + RISE;
    var shacklePath = new THREE.CurvePath();
    shacklePath.add(new THREE.LineCurve3(V(-LEG, y0), V(-LEG, y1)));
    logoArch(THREE, function (x, y) { return V(x * LEG / 14, y1 + y * 0.8); }).curves.forEach(function (c) { shacklePath.add(c); });
    shacklePath.add(new THREE.LineCurve3(V(LEG, y1), V(LEG, y0)));
    var pivot = new THREE.Group();
    pivot.position.set(-LEG, 0, 0);
    lock.add(pivot);
    var shackle = s.mesh(new THREE.TubeGeometry(shacklePath, 140, 1.8, 18, false), s.mat(C.light, { roughness: 0.28, metalness: 0.35 }), pivot);
    shackle.position.set(LEG, 0, 0);

    // Key (amber = the one action). Built along +z with its tip at z = 0,
    // blade teeth hanging down (-y) to match the keyhole slot.
    var key = new THREE.Group();
    lock.add(key);
    var keyMat = s.mat(C.cta, { roughness: 0.32, metalness: 0.3 });
    var bow = s.mesh(new THREE.TorusGeometry(2.4, 0.85, 12, 28), keyMat, key);
    bow.rotation.y = Math.PI / 2;
    bow.position.z = 11.9;
    var shaft = s.mesh(new THREE.CylinderGeometry(0.6, 0.6, 9.5, 12), keyMat, key);
    shaft.rotation.x = Math.PI / 2;
    shaft.position.z = 4.75;
    [[1.2, 1.4], [3.0, 1.0], [4.7, 1.6]].forEach(function (tooth) {
      var m = s.mesh(new THREE.BoxGeometry(0.5, tooth[1], 1.1), keyMat, key);
      m.position.set(0, -0.5 - tooth[1] / 2, tooth[0]);
    });

    var KEY_Y = KY - 1.5;
    var IDLE = { x: 13, y: 6, z: 12, ry: 0.95 };
    var READY = { x: 0, y: KEY_Y, z: FRONT + 3.5, ry: 0 };
    var IN_Z = FRONT - 5.5;

    var cur = { p: 0, i: 0, turn: 0, open: 0 }, tgt = { p: 0, i: 0, turn: 0, open: 0 };
    var mode = 'ready', lastProgress = 0, shakeAt = -10, now = 0;

    host.tmScene = {
      setProgress: function (v) { lastProgress = clamp01(v); if (mode === 'ready') tgt.p = lastProgress; },
      attempt: function () { mode = 'attempt'; tgt.p = 1; },
      deny: function () { mode = 'deny'; shakeAt = now; },
      grant: function () { mode = 'grant'; tgt.p = 1; }
    };

    function frame(t, dt) {
      now = t;
      if (mode === 'attempt') {            // insert, then a hesitant quarter-turn while the server checks
        tgt.i = 1;
        tgt.turn = cur.i > 0.9 ? 0.3 : 0;
      } else if (mode === 'grant') {       // insert → full turn → shackle lifts and swings open
        tgt.i = 1;
        tgt.turn = cur.i > 0.9 ? 1 : 0;
        tgt.open = cur.turn > 0.92 ? 1 : 0;
      } else if (mode === 'deny') {        // turn back, pull out, head-shake
        tgt.turn = 0;
        tgt.open = 0;
        tgt.i = cur.turn < 0.08 ? 0 : 1;
        if (cur.i < 0.05 && t - shakeAt > 0.7) { mode = 'ready'; tgt.p = lastProgress; }
      }
      cur.p = s.approach(cur.p, tgt.p, dt, 5);
      cur.i = s.approach(cur.i, tgt.i, dt, 7);
      cur.turn = s.approach(cur.turn, tgt.turn, dt, 6);
      cur.open = s.approach(cur.open, tgt.open, dt, 4.5);

      var e = easeInOut(cur.p), bob = s.reduced ? 0 : Math.sin(t * 1.6) * 0.7 * (1 - e);
      key.position.set(
        IDLE.x + (READY.x - IDLE.x) * e,
        IDLE.y + (READY.y - IDLE.y) * e + bob,
        IDLE.z + (READY.z - IDLE.z) * e + (IN_Z - READY.z) * cur.i
      );
      key.rotation.set(0, IDLE.ry * (1 - e), -cur.turn * Math.PI / 2 + (s.reduced ? 0 : Math.sin(t * 1.1) * 0.25 * (1 - e)));

      pivot.position.y = clamp01(cur.open / 0.45) * 4.5;
      pivot.rotation.y = -clamp01((cur.open - 0.45) / 0.55) * 1.9;
      glowMat.emissiveIntensity = 0.9 + cur.open * 0.9 + (mode === 'attempt' && !s.reduced ? Math.sin(t * 8) * 0.25 : 0);

      var since = t - shakeAt;
      lock.rotation.z = since < 0.7 && !s.reduced ? Math.sin(since * 38) * 0.07 * (1 - since / 0.7) : 0;
      lock.position.y = s.reduced ? 0 : Math.sin(t * 1.2) * 0.4;
      s.group.rotation.y = s.pointer.x * 0.35 + (s.reduced ? 0 : Math.sin(t * 0.3) * 0.12);
      s.group.rotation.x = s.pointer.y * 0.08;
    }
    s.run(frame);
  }

  /* ---------- Register: build the arch, keystone last ---------- */
  function initKeystone(host) {
    var s = createStage(host, { camera: [14, 20, 76], lookAt: [0, 8, 0], groundY: -6, maxDpr: 1.5, maxFps: 30 });
    if (!s) return;
    var THREE = s.THREE, C = s.C;
    var arch = new THREE.Group();
    s.group.add(arch);

    var path = logoArch(THREE);
    var N = 9, KEY = 4, THICK = 4.4, DEPTH = 6;
    var seg = path.getLength() / N;

    // Abutments — the ground you build out from
    [[-14, C.dark], [14, C.light]].forEach(function (a) {
      var m = s.mesh(new THREE.BoxGeometry(7, 6, DEPTH + 1.5), s.mat(a[1], { roughness: 0.55 }), arch);
      m.position.set(a[0], -3, 0);
      m.receiveShadow = true;
    });

    // Voussoirs along the logo curve, coloured dark→light like the logo's
    // gradient; the centre one is the amber keystone (the logo's dot).
    var blocks = [], ghostMats = [], edgeAlpha = 0.32;
    for (var i = 0; i < N; i++) {
      var u = (i + 0.5) / N, pt = path.getPointAt(u), tan = path.getTangentAt(u);
      var isKey = i === KEY;
      var geo = new THREE.BoxGeometry(seg * 0.93, isKey ? THICK + 1.4 : THICK, isKey ? DEPTH + 0.8 : DEPTH);
      var color = isKey ? C.cta : C.dark.clone().lerp(C.light, i / (N - 1));
      var material = s.mat(color, { roughness: 0.5, transparent: true, opacity: 0, emissive: isKey ? C.cta : 0x000000, emissiveIntensity: 0 });
      var mesh = s.mesh(geo, material, arch);
      var ang = Math.atan2(tan.y, tan.x);
      // blueprint ghost of where the stone will go
      var ghost = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ color: C.light, transparent: true, opacity: 0.06, depthWrite: false }));
      var edges = new THREE.LineSegments(new THREE.EdgesGeometry(geo), new THREE.LineBasicMaterial({ color: C.light, transparent: true, opacity: 0.32 }));
      ghostMats.push(ghost.material, edges.material);
      [ghost, edges].forEach(function (g) { g.position.copy(pt); g.rotation.z = ang; arch.add(g); });
      blocks.push({ mesh: mesh, rest: pt, ang: ang, s: 0, v: 0, target: 0, side: i < KEY ? -1 : 1, isKey: isKey, ghost: [ghost, edges] });
    }

    // Blueprint lines: light purple reads on the dark stage, brand purple on the lavender one
    s.onTheme(function (dark) {
      edgeAlpha = dark ? 0.32 : 0.45;
      ghostMats.forEach(function (m) { m.color.copy(dark ? C.light : C.primary); });
    });

    var ball = s.mesh(new THREE.SphereGeometry(1.5, 32, 20), s.mat(C.light, { roughness: 0.3 }), arch);
    ball.scale.setScalar(0.001);

    var mode = 'build', shakeAt = -10, rollAt = -10, now = 0;
    function place(v) {
      // 5 checks: the first four lay pairs from both ends inward, the last drops the keystone
      var done = Math.round(clamp01(v) * 5);
      blocks.forEach(function (b, idx) {
        b.target = b.isKey ? (done >= 5 ? 1 : 0) : (Math.min(idx, N - 1 - idx) < Math.min(done, 4) ? 1 : 0);
      });
    }
    host.tmScene = {
      setProgress: function (v) { if (mode !== 'grant') place(v); },
      attempt: function () { mode = 'attempt'; },
      deny: function () { mode = 'build'; shakeAt = now; },
      grant: function () { place(1); mode = 'grant'; rollAt = now + 0.35; }
    };

    function frame(t, dt) {
      now = t;
      blocks.forEach(function (b) {
        if (s.reduced) { b.s = b.target; }
        else {                                  // springy drop with a little thud
          b.v += (140 * (b.target - b.s) - 15 * b.v) * dt;
          b.s += b.v * dt;
        }
        var k = 1 - b.s;
        b.mesh.position.set(b.rest.x + k * b.side * 5, b.rest.y + k * 18, b.rest.z);
        b.mesh.rotation.z = b.ang + k * b.side * 0.7;
        b.mesh.material.opacity = clamp01(b.s * 1.6);
        b.mesh.visible = b.mesh.material.opacity > 0.01;
        b.ghost[1].material.opacity = edgeAlpha * (1 - clamp01(b.s));
        if (b.isKey) {
          b.mesh.material.emissiveIntensity = mode === 'grant' ? 0.8
            : mode === 'attempt' && !s.reduced ? 0.35 + Math.sin(t * 8) * 0.25 : 0.15 * b.s;
        }
      });

      // Success: a learner rolls across the finished bridge
      var r = clamp01((t - rollAt) / 1.5);
      if (mode === 'grant' && t >= rollAt) {
        var u = s.reduced ? 1 : easeInOut(r), p = path.getPointAt(u), tn = path.getTangentAt(u);
        var off = THICK / 2 + 1.7;
        ball.position.set(p.x - tn.y * off, p.y + tn.x * off, 0);
        ball.rotation.z = -u * path.getLength() / 1.5;
        ball.scale.setScalar(1);
      }

      var since = t - shakeAt;
      arch.rotation.z = since < 0.7 && !s.reduced ? Math.sin(since * 34) * 0.035 * (1 - since / 0.7) : 0;
      s.group.rotation.y = s.pointer.x * 0.35 + (s.reduced ? 0 : Math.sin(t * 0.3) * 0.14);
      s.group.rotation.x = s.pointer.y * 0.08;
    }
    s.run(frame);
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
    document.querySelectorAll('[data-ds-scene="lock"]').forEach(initLock);
    document.querySelectorAll('[data-ds-scene="keystone"]').forEach(initKeystone);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
