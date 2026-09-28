/**
 * TutorMind onboarding — five steps, and every answer reaches the tutor.
 *   1 About you   → education_level, country
 *   2 Subjects    → field_of_study
 *   3 Goal & you  → learning_goal, interests, response_style
 *   4 Quick check → knowledge_level
 *   5 Ready       → first question, prefilled in the chat (never auto-sent)
 * Markup: onboarding.php. Styles: onboarding.css on tm-ds.css.
 * Data: onboarding-data.js. Saves through api/user_onboarding.php.
 */
(function () {
  'use strict';

  var D = window.TM_ONBOARDING_DATA;
  var TOTAL = 5;
  var UPDATE = window.TUTORMIND_UPDATE_MODE === true;
  var USER = window.TUTORMIND_USER_ID === undefined || window.TUTORMIND_USER_ID === null ? 'anon' : window.TUTORMIND_USER_ID;
  var STORE_KEY = 'tutormind_onboarding_v3:' + USER;
  var CROSS_MS = 1900; // time for the learner to roll across the finished bridge

  var COUNTRIES = ['Ghana', 'Nigeria', 'Kenya', 'South Africa', 'Uganda', 'Tanzania', 'Rwanda', 'Cameroon',
    'Côte d’Ivoire', 'Senegal', 'Zambia', 'Zimbabwe', 'Ethiopia', 'Egypt', 'United Kingdom', 'United States',
    'Canada', 'India', 'Pakistan', 'Philippines', 'Australia', 'Germany', 'France'];
  var TZ_COUNTRY = {
    'Africa/Accra': 'Ghana', 'Africa/Lagos': 'Nigeria', 'Africa/Nairobi': 'Kenya', 'Africa/Johannesburg': 'South Africa',
    'Africa/Kampala': 'Uganda', 'Africa/Dar_es_Salaam': 'Tanzania', 'Africa/Kigali': 'Rwanda', 'Africa/Douala': 'Cameroon',
    'Africa/Abidjan': 'Côte d’Ivoire', 'Africa/Dakar': 'Senegal', 'Africa/Lusaka': 'Zambia', 'Africa/Harare': 'Zimbabwe',
    'Africa/Addis_Ababa': 'Ethiopia', 'Africa/Cairo': 'Egypt', 'Europe/London': 'United Kingdom',
    'America/New_York': 'United States', 'America/Chicago': 'United States', 'America/Denver': 'United States',
    'America/Los_Angeles': 'United States', 'America/Toronto': 'Canada', 'America/Vancouver': 'Canada',
    'Asia/Kolkata': 'India', 'Asia/Calcutta': 'India', 'Asia/Karachi': 'Pakistan', 'Asia/Manila': 'Philippines',
    'Europe/Berlin': 'Germany', 'Europe/Paris': 'France'
  };
  var SUBJECT_LABELS = {
    'mathematics': 'Mathematics', 'science': 'Science', 'languages': 'Languages and writing',
    'computer-science': 'Computer science', 'social-studies': 'Social studies', 'business': 'Business and finance'
  };
  var EDU_LABELS = { high: 'SHS', college: 'University', adult: 'Adult learner', other: null };
  var GOAL_LABELS = {
    homework_help: 'Homework', exam_prep: 'Exam prep', concept_mastery: 'Deep understanding',
    catch_up: 'Catching up', get_ahead: 'Getting ahead', general_learning: 'Learning for fun'
  };
  var LEVELS = {
    beginner: { label: 'Beginner', msg: 'Your tutor will start from the foundations and build up step by step.' },
    intermediate: { label: 'Intermediate', msg: 'Your tutor will build on what you know and fill in the gaps.' },
    advanced: { label: 'Advanced', msg: 'Your tutor will skip the basics and take you deeper.' }
  };
  // Keys a restored or preloaded profile must hold as arrays
  var ARRAY_KEYS = ['shsElectives', 'customSubjects', 'subjects', 'interests'];

  var state = {
    educationLevel: null, enrollmentStatus: null, schoolName: '', country: '',
    shsProgram: null, shsElectives: [], universityProgram: '', customSubjects: [], subjects: [],
    learningGoal: null, interests: [], responseStyle: 'concise',
    assessmentResults: null, knowledgeLevel: null
  };
  var existingProfile = null; // update mode: saved profile_data, kept so a re-save doesn't drop old keys
  var step = 1;
  var firstPrompt = '';
  var quiz = { questions: [], index: 0, correct: 0, answered: false, done: false };
  var saving = false;

  function $(id) { return document.getElementById(id); }
  function stepEl(n) { return document.querySelector('.onb-step[data-step="' + n + '"]'); }
  function scene(method, arg) {
    var host = document.querySelector('[data-ds-scene="keystone"]');
    if (host && host.tmScene) host.tmScene[method](arg);
  }
  function icon(name) {
    return '<svg class="ds-i" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
  }

  /* ---------- Persistence ---------- */
  function save() {
    if (UPDATE) return;
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify({ step: step, state: state, firstPrompt: firstPrompt }));
    } catch (e) { /* storage blocked — progress just won't survive a refresh */ }
  }

  function restore() {
    var saved = null;
    try {
      // Retired wizard keys; the unscoped one may hold another account's answers
      localStorage.removeItem('tutormind_wizard_v2');
      localStorage.removeItem('tutormind_wizard_v2:' + USER);
      saved = JSON.parse(localStorage.getItem(STORE_KEY));
    } catch (e) { return; }
    if (!saved || typeof saved !== 'object' || !saved.state || typeof saved.state !== 'object') return;
    Object.keys(state).forEach(function (k) {
      if (saved.state[k] !== undefined) state[k] = saved.state[k];
    });
    var n = Number(saved.step);
    if (Number.isInteger(n) && n >= 1 && n <= TOTAL) step = n;
    if (typeof saved.firstPrompt === 'string') firstPrompt = saved.firstPrompt;
  }

  function sanitize() {
    ARRAY_KEYS.forEach(function (k) {
      state[k] = Array.isArray(state[k]) ? state[k].filter(function (v) { return typeof v === 'string'; }) : [];
    });
    ['schoolName', 'country', 'universityProgram'].forEach(function (k) {
      if (typeof state[k] !== 'string') state[k] = '';
    });
    if (state.responseStyle !== 'detailed') state.responseStyle = 'concise';
  }

  /* ---------- Chips ---------- */
  function syncChips() {
    document.querySelectorAll('[data-single]').forEach(function (row) {
      var key = row.getAttribute('data-single');
      row.querySelectorAll('.ds-chip').forEach(function (chip) {
        chip.setAttribute('aria-pressed', String(state[key] === chip.getAttribute('data-value')));
      });
    });
    document.querySelectorAll('[data-multi]').forEach(function (row) {
      var list = state[row.getAttribute('data-multi')];
      row.querySelectorAll('.ds-chip').forEach(function (chip) {
        chip.setAttribute('aria-pressed', String(list.indexOf(chip.getAttribute('data-value')) !== -1));
      });
    });
  }

  function bindChips() {
    document.addEventListener('click', function (e) {
      var chip = e.target.closest('.ds-chip[data-value]');
      if (!chip) return;
      var single = chip.closest('[data-single]');
      var multi = chip.closest('[data-multi]');
      var value = chip.getAttribute('data-value');
      if (single) {
        var key = single.getAttribute('data-single');
        state[key] = value;
        if (key === 'educationLevel') onLevelChange();
        if (key === 'shsProgram') onProgramChange();
      } else if (multi) {
        var listKey = multi.getAttribute('data-multi');
        var list = state[listKey];
        var at = list.indexOf(value);
        if (at === -1) list.push(value); else list.splice(at, 1);
      } else {
        return;
      }
      syncChips();
      save();
    });
  }

  /* ---------- Tags (courses, topics, interests) ---------- */
  var ADD_TARGETS = { 'uni-subject': 'customSubjects', 'gen-subject': 'customSubjects', 'interest-input': 'interests' };

  function renderTags() {
    document.querySelectorAll('[data-tags]').forEach(function (ul) {
      var key = ul.getAttribute('data-tags');
      ul.innerHTML = '';
      state[key].forEach(function (text, i) {
        var li = document.createElement('li');
        li.className = 'onb-tag';
        var label = document.createElement('span');
        label.textContent = text;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Remove ' + text);
        btn.innerHTML = icon('close');
        btn.addEventListener('click', function () {
          state[key].splice(i, 1);
          renderTags();
          save();
        });
        li.appendChild(label);
        li.appendChild(btn);
        ul.appendChild(li);
      });
    });
  }

  // Adds whatever is typed in the field; returns true if something was added
  function addFrom(inputId) {
    var input = $(inputId);
    var key = ADD_TARGETS[inputId];
    var text = input.value.trim().slice(0, 60);
    if (!text) return false;
    var list = state[key];
    var dupe = list.some(function (v) { return v.toLowerCase() === text.toLowerCase(); });
    if (!dupe && list.length < 20) list.push(text);
    input.value = '';
    renderTags();
    save();
    return true;
  }

  function bindTags() {
    Object.keys(ADD_TARGETS).forEach(function (id) {
      $(id).addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addFrom(id); }
      });
    });
    document.querySelectorAll('[data-add]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        addFrom(btn.getAttribute('data-add'));
        $(btn.getAttribute('data-add')).focus();
      });
    });
  }

  /* ---------- Step 1: About you ---------- */
  function onLevelChange() {
    var level = state.educationLevel;
    var college = level === 'college';
    $('enrollment-group').hidden = !college;
    if (college && !state.enrollmentStatus) state.enrollmentStatus = 'enrolled';
    $('school-field').hidden = !(college || level === 'high');
    updateSchoolLabel();
    var school = $('school-input');
    if (college) school.setAttribute('list', 'university-list'); else school.removeAttribute('list');
  }

  function updateSchoolLabel() {
    var label = state.educationLevel === 'college'
      ? (state.enrollmentStatus === 'graduated' ? 'University you attended' : 'Your university')
      : 'Your school';
    $('school-label').textContent = label;
  }

  function initAbout() {
    var uniList = $('university-list');
    D.universities.forEach(function (u) { var o = document.createElement('option'); o.value = u; uniList.appendChild(o); });
    var countryList = $('country-list');
    COUNTRIES.forEach(function (c) { var o = document.createElement('option'); o.value = c; countryList.appendChild(o); });

    // Guess only for a fresh setup: in update mode step 1 is never shown, so a
    // guess would be saved without the learner ever seeing it.
    if (!state.country && !UPDATE) {
      try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        state.country = TZ_COUNTRY[tz] || (tz.indexOf('Australia/') === 0 ? 'Australia' : '');
      } catch (e) { /* no Intl — leave it blank */ }
    }
    $('school-input').value = state.schoolName;
    $('country-input').value = state.country;
    $('school-input').addEventListener('input', function (e) { state.schoolName = e.target.value; save(); });
    $('country-input').addEventListener('input', function (e) { state.country = e.target.value; save(); });
    document.querySelector('[data-single="enrollmentStatus"]').addEventListener('click', function () {
      setTimeout(updateSchoolLabel);
    });
    onLevelChange();
  }

  /* ---------- Step 2: Subjects ---------- */
  function branch() {
    if (state.educationLevel === 'high') return 'high';
    if (state.educationLevel === 'college') return 'college';
    return 'general';
  }

  function renderPrograms() {
    var row = $('shs-programs');
    row.innerHTML = '';
    row.setAttribute('data-single', 'shsProgram');
    Object.keys(D.shsPrograms).forEach(function (id) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'ds-chip';
      chip.setAttribute('data-value', id);
      chip.setAttribute('aria-pressed', 'false');
      chip.textContent = D.shsPrograms[id].name;
      row.appendChild(chip);
    });
  }

  function onProgramChange() {
    var program = D.shsPrograms[state.shsProgram];
    var group = $('shs-electives-group');
    var row = $('shs-electives');
    row.innerHTML = '';
    group.hidden = !program;
    if (!program) return;
    var ids = program.electives.map(function (e) { return e.id; });
    state.shsElectives = state.shsElectives.filter(function (id) { return ids.indexOf(id) !== -1; });
    row.setAttribute('data-multi', 'shsElectives');
    program.electives.forEach(function (el) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'ds-chip';
      chip.setAttribute('data-value', el.id);
      chip.setAttribute('aria-pressed', 'false');
      chip.textContent = el.name;
      row.appendChild(chip);
    });
    syncChips();
  }

  function showSubjects() {
    var b = branch();
    document.querySelectorAll('.onb-branch').forEach(function (el) {
      el.hidden = el.getAttribute('data-branch') !== b;
    });
    var graduated = state.enrollmentStatus === 'graduated';
    var copy = {
      high: ['What’s your SHS programme?', 'Pick your programme, then your electives.'],
      college: graduated
        ? ['What did you study?', 'Add the courses you’d like to revisit. You can ask about anything else too.']
        : ['What are you studying?', 'Add the courses you want help with. You can ask about anything else too.'],
      general: ['What do you want to learn?', 'Pick a few subjects, or add something specific.']
    }[b];
    $('s2-title').textContent = copy[0];
    $('s2-sub').textContent = copy[1];
    $('uni-subject-label').textContent = graduated ? 'Courses you’d like to revisit' : 'Courses you want help with';
    if (b === 'high') onProgramChange();
  }

  /* ---------- Step 4: Quick check ---------- */
  function pickBank() {
    var bank = D.questionBank;
    var custom = state.customSubjects.join(' ').toLowerCase();
    function byKeyword(text) {
      for (var i = 0; i < D.uniKeywords.length; i++) {
        var k = D.uniKeywords[i];
        if (k.keys.some(function (w) { return text.indexOf(w) !== -1; })) return k.bank;
      }
      return null;
    }
    var b = branch();
    if (b === 'college') {
      return byKeyword((custom + ' ' + state.universityProgram).toLowerCase()) || 'aptitude';
    }
    if (b === 'high') {
      var el = state.shsElectives;
      if (state.shsProgram === 'business' || el.indexOf('financial-accounting') !== -1 || el.indexOf('business-management') !== -1) return 'business';
      if (state.shsProgram && bank[state.shsProgram]) return state.shsProgram;
      return 'aptitude';
    }
    for (var i = 0; i < state.subjects.length; i++) {
      if (bank[state.subjects[i]]) return state.subjects[i];
    }
    return byKeyword(custom) || 'aptitude';
  }

  function bankTopic(key) {
    if (key === 'aptitude') return 'everyday reasoning';
    if (key.indexOf('uni-') === 0) key = key.slice(4);
    var names = { cs: 'computer science', 'general-science': 'science', 'home-economics': 'home economics', 'visual-arts': 'visual arts', 'computer-science': 'computer science', 'social-studies': 'social studies' };
    return names[key] || key.replace(/-/g, ' ');
  }

  function startQuiz() {
    var key = pickBank();
    quiz = { questions: D.questionBank[key].slice(0, 3), index: 0, correct: 0, answered: false, done: false, topic: bankTopic(key) };
    $('s4-sub').textContent = 'Three questions on ' + quiz.topic + ' so your tutor knows where to start. It isn’t graded.';
    $('quiz').hidden = false;
    $('quiz-result').hidden = true;
    $('quiz-skip').hidden = false;
    renderQuestion();
  }

  function renderQuestion() {
    var q = quiz.questions[quiz.index];
    quiz.answered = false;
    $('quiz-count').textContent = 'Question ' + (quiz.index + 1) + ' of ' + quiz.questions.length;
    $('quiz-q').textContent = q.text;
    $('quiz-feedback').textContent = '';
    var opts = $('quiz-opts');
    opts.innerHTML = '';
    q.options.forEach(function (text, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'onb-opt';
      var label = document.createElement('span');
      label.textContent = text;
      btn.appendChild(label);
      btn.insertAdjacentHTML('beforeend', icon(i === q.correct ? 'check' : 'close'));
      btn.addEventListener('click', function () { answer(i); });
      opts.appendChild(btn);
    });
    var next = $('quiz-next');
    next.disabled = true;
    next.innerHTML = (quiz.index === quiz.questions.length - 1 ? 'See where you’ll start ' : 'Next question ') + icon('arrow').replace('ds-i"', 'ds-i ds-i-arrow"');
  }

  function answer(i) {
    if (quiz.answered) return;
    quiz.answered = true;
    var q = quiz.questions[quiz.index];
    var buttons = $('quiz-opts').querySelectorAll('.onb-opt');
    buttons.forEach(function (b) { b.disabled = true; });
    buttons[q.correct].classList.add('is-correct');
    if (i === q.correct) {
      quiz.correct++;
      $('quiz-feedback').textContent = 'That’s right.';
    } else {
      buttons[i].classList.add('is-wrong');
      $('quiz-feedback').textContent = 'The answer is ' + q.options[q.correct] + '.';
    }
    $('quiz-next').disabled = false;
    $('quiz-next').focus();
  }

  function finishQuiz() {
    var total = quiz.questions.length;
    var level = quiz.correct === total ? 'advanced' : quiz.correct * 2 >= total ? 'intermediate' : 'beginner';
    quiz.done = true;
    state.knowledgeLevel = level;
    state.assessmentResults = { score: quiz.correct, total: total, level: level, topic: quiz.topic };
    showResult();
    save();
  }

  function showResult() {
    var level = LEVELS[state.knowledgeLevel];
    $('quiz').hidden = true;
    $('quiz-skip').hidden = true;
    $('quiz-result').hidden = false;
    $('result-level').textContent = level.label;
    var score = state.assessmentResults && state.assessmentResults.total
      ? ' You got ' + state.assessmentResults.score + ' of ' + state.assessmentResults.total + '.' : '';
    $('result-msg').textContent = level.msg + score;
    var next = $('quiz-next');
    next.disabled = false;
    next.innerHTML = 'Continue ' + icon('arrow').replace('ds-i"', 'ds-i ds-i-arrow"');
  }

  function enterQuiz() {
    if (quiz.done || (state.knowledgeLevel && state.assessmentResults && !state.assessmentResults.skipped)) {
      quiz.done = true;
      showResult();
    } else {
      startQuiz();
    }
  }

  function bindQuiz() {
    $('quiz-next').addEventListener('click', function () {
      if (quiz.done) { go(5); return; }
      if (!quiz.answered) return;
      if (quiz.index < quiz.questions.length - 1) {
        quiz.index++;
        renderQuestion();
        $('quiz-q').focus();
      } else {
        finishQuiz();
      }
    });
    $('quiz-skip').addEventListener('click', function () {
      state.assessmentResults = { skipped: true };
      state.knowledgeLevel = null;
      quiz.done = false;
      save();
      go(5);
    });
    $('quiz-q').setAttribute('tabindex', '-1');
  }

  /* ---------- Step 5: Ready ---------- */
  function subjectNames() {
    var b = branch();
    if (b === 'high') {
      var program = D.shsPrograms[state.shsProgram];
      var names = program ? program.electives.filter(function (e) { return state.shsElectives.indexOf(e.id) !== -1; })
        .map(function (e) { return e.name; }) : [];
      return names.length ? names : (program ? [program.name] : []);
    }
    if (b === 'college') return state.customSubjects.slice();
    return state.subjects.map(function (s) { return SUBJECT_LABELS[s]; }).filter(Boolean).concat(state.customSubjects);
  }

  function starters() {
    var names = subjectNames();
    var a = names[0] || 'what I’m studying';
    var b = names[1] || a;
    var byGoal = {
      homework_help: ['Help me work through my ' + a + ' homework', 'I’m stuck on a ' + b + ' question. Can you guide me?'],
      exam_prep: ['I have a ' + a + ' exam coming up. Quiz me to find my weak spots', 'Give me an exam-style ' + b + ' question'],
      concept_mastery: ['Help me really understand a tricky ' + a + ' topic', 'Why does ' + b + ' work the way it does? Start with the basics'],
      catch_up: ['Help me catch up on the basics of ' + a, 'Check what I already know in ' + b + ' and fill the gaps'],
      get_ahead: ['What comes next after what I’ve learned in ' + a + '?', 'Teach me something beyond my level in ' + b],
      general_learning: ['Teach me something surprising about ' + a, 'Explain an interesting idea from ' + b]
    };
    var list = (byGoal[state.learningGoal] || byGoal.general_learning).slice();
    if (a === b) list[1] = 'Explain a ' + a + ' topic I find confusing';
    list.push('Explain a topic I’m stuck on, step by step');
    return list;
  }

  function showReady() {
    var edu = EDU_LABELS[state.educationLevel];
    var names = subjectNames();
    var parts = [];
    if (edu) parts.push(edu);
    if (names.length) parts.push(names.slice(0, 2).join(' and ') + (names.length > 2 ? ' +' + (names.length - 2) : ''));
    if (GOAL_LABELS[state.learningGoal]) parts.push(GOAL_LABELS[state.learningGoal]);
    parts.push(state.responseStyle === 'detailed' ? 'Fuller explanations' : 'Short answers');
    $('recap').textContent = parts.join(' · ');

    var box = $('starters');
    box.innerHTML = '';
    var list = starters();
    if (!firstPrompt) firstPrompt = list[0];
    list.forEach(function (text) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'onb-starter';
      btn.setAttribute('aria-pressed', String(text === firstPrompt));
      btn.insertAdjacentHTML('afterbegin', icon('arrow'));
      var label = document.createElement('span');
      label.textContent = text;
      btn.appendChild(label);
      btn.addEventListener('click', function () {
        firstPrompt = text;
        $('first-prompt').value = text;
        syncStarters();
        save();
      });
      box.appendChild(btn);
    });
    $('first-prompt').value = firstPrompt;
  }

  function syncStarters() {
    document.querySelectorAll('.onb-starter').forEach(function (btn) {
      btn.setAttribute('aria-pressed', String(btn.textContent === firstPrompt));
    });
  }

  /* ---------- Navigation ---------- */
  var VALIDATE = {
    1: function () {
      if (!state.educationLevel) return 'Choose where you are in your studies.';
    },
    2: function () {
      var b = branch();
      if (b === 'high') {
        if (!state.shsProgram) return 'Pick your programme.';
        if (!state.shsElectives.length) return 'Pick at least one elective.';
      } else if (b === 'college') {
        addFrom('uni-subject');
        if (!state.customSubjects.length) return 'Add at least one course you want help with.';
      } else {
        addFrom('gen-subject');
        if (!state.subjects.length && !state.customSubjects.length) return 'Pick at least one subject, or add your own.';
      }
    },
    3: function () {
      addFrom('interest-input');
      if (!state.learningGoal) return 'Pick what you mostly want help with.';
    }
  };

  function setError(n, msg) {
    var el = $('err-' + n);
    if (el) el.textContent = msg || '';
  }

  function go(n) {
    step = n;
    document.querySelectorAll('.onb-step').forEach(function (el) {
      el.hidden = Number(el.getAttribute('data-step')) !== n;
    });
    $('onb-progress-label').textContent = 'Step ' + n + ' of ' + TOTAL;
    document.querySelectorAll('.onb-progress__bar li').forEach(function (li, i) {
      li.classList.toggle('is-done', i < n - 1);
      li.classList.toggle('is-now', i === n - 1);
    });
    // Each finished step lays a pair of stones; the keystone waits for "Start learning"
    scene('setProgress', (n - 1) / TOTAL);

    if (n === 2) showSubjects();
    if (n === 4) enterQuiz();
    if (n === 5) showReady();

    var title = stepEl(n).querySelector('.onb-title');
    var card = $('onb-card');
    if (card.getBoundingClientRect().top < 0) card.scrollIntoView({ block: 'start' });
    if (title && document.body.classList.contains('onb-ready')) title.focus({ preventScroll: true });
    save();
  }

  function bindNav() {
    document.querySelectorAll('[data-next]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var n = Number(btn.closest('.onb-step').getAttribute('data-step'));
        var msg = VALIDATE[n] && VALIDATE[n]();
        setError(n, msg);
        if (msg) { scene('deny'); return; }
        if (UPDATE && n === 3) { finish(); return; }
        go(n + 1);
      });
    });
    document.querySelectorAll('[data-back]').forEach(function (btn) {
      btn.addEventListener('click', function () { go(Math.max(1, step - 1)); });
    });
    // An error describes the last Continue press; once the learner acts, it no longer applies
    document.querySelectorAll('.onb-step').forEach(function (el) {
      var clear = function () { setError(el.getAttribute('data-step'), ''); };
      el.addEventListener('click', function (e) { if (e.target.closest('.ds-chip, [data-add]')) clear(); });
      el.addEventListener('input', clear);
    });
    $('first-prompt').addEventListener('input', function (e) {
      firstPrompt = e.target.value;
      syncStarters();
      save();
    });
    $('finish-btn').addEventListener('click', finish);
    $('uni-program').addEventListener('input', function (e) { state.universityProgram = e.target.value; save(); });
  }

  /* ---------- Save ---------- */
  function payload() {
    var data = Object.assign({}, existingProfile || {}, state);
    ['schoolName', 'country', 'universityProgram'].forEach(function (k) {
      data[k] = data[k] ? String(data[k]).trim() : null;
    });
    if (!data.assessmentResults) delete data.assessmentResults;
    return data;
  }

  function showSaveError(msg) {
    var box = UPDATE ? null : $('save-error');
    if (box) {
      box.querySelector('span').textContent = msg;
      box.hidden = false;
    } else {
      setError(3, msg);
    }
  }

  function finish() {
    if (saving) return;
    saving = true;
    var btn = UPDATE ? $('s3-next') : $('finish-btn');
    var btnHTML = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Saving…';
    if (!UPDATE) $('save-error').hidden = true;
    scene('attempt');

    fetch('api/user_onboarding.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload())
    }).then(function (res) {
      if (res.status === 401) {
        var err = new Error('Your session has expired. Log in again to finish setting up.');
        err.expired = true;
        throw err;
      }
      return res.json();
    }).then(function (result) {
      if (!result || !result.success) throw new Error((result && result.error) || 'Your answers weren’t saved. Please try again.');
      try {
        localStorage.removeItem(STORE_KEY);
        var prompt = ($('first-prompt').value || '').trim();
        if (!UPDATE && prompt) sessionStorage.setItem('tm_first_prompt', prompt.slice(0, 500));
      } catch (e) { /* storage blocked — chat simply opens empty */ }
      btn.textContent = UPDATE ? 'Saved' : 'Your tutor is ready';
      scene('grant');
      setTimeout(function () { window.location.href = 'chat'; }, CROSS_MS);
    }).catch(function (error) {
      console.error('Onboarding save error:', error);
      saving = false;
      scene('deny');
      btn.disabled = false;
      btn.innerHTML = btnHTML;
      showSaveError(error.message || 'Your answers weren’t saved. Please try again.');
      if (error.expired) setTimeout(function () { window.location.href = 'login'; }, 2500);
    });
  }

  /* ---------- Update mode ---------- */
  // A returning learner who finished the old wizard before interests existed:
  // only step 3, preloaded with their saved answers, then straight back to chat.
  function enterUpdateMode() {
    var existing = window.TUTORMIND_EXISTING_PROFILE;
    if (existing && typeof existing === 'object') {
      existingProfile = existing;
      Object.keys(state).forEach(function (k) {
        if (existing[k] !== undefined && existing[k] !== null) state[k] = existing[k];
      });
    }
    $('onb-progress').hidden = true;
    $('s3-title').textContent = 'One more thing';
    $('s3-sub').textContent = 'Your tutor now uses your goal and interests to pick examples. Tell it about you.';
    var back = stepEl(3).querySelector('[data-back]');
    if (back) back.hidden = true;
    $('s3-next').innerHTML = 'Save ' + icon('check');
  }

  /* ---------- Boot ---------- */
  function init() {
    if (!D) { console.error('onboarding-data.js did not load'); return; }
    var prefs = window.TUTORMIND_SAVED_PREFS || {};
    if (typeof prefs.country === 'string') state.country = prefs.country;
    if (prefs.responseStyle === 'detailed' || prefs.responseStyle === 'concise') state.responseStyle = prefs.responseStyle;
    if (UPDATE) enterUpdateMode(); else restore();
    sanitize();

    renderPrograms();
    initAbout();
    bindChips();
    bindTags();
    bindQuiz();
    bindNav();
    syncChips();
    renderTags();
    $('uni-program').value = state.universityProgram;
    document.querySelectorAll('.onb-title').forEach(function (t) { t.setAttribute('tabindex', '-1'); });

    go(UPDATE ? 3 : step);
    // Focus moves to each new step's heading only after the first render,
    // so the page doesn't jump on load.
    document.body.classList.add('onb-ready');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
