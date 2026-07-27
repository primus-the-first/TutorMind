#!/usr/bin/env node
/**
 * CI smoke check: catches the exact bug class from the 2026-07-27 onboarding
 * incident — a JS file calling document.getElementById('some-id') where
 * 'some-id' doesn't exist (yet) in the PHP page(s) that load it. That
 * mismatch throws at runtime with no visible error, silently breaking every
 * click handler bound after the crash point (see project_onboarding_continue_bug
 * in memory for the full incident writeup).
 *
 * php -l / node --check don't catch this — both files are syntactically
 * valid; the failure only exists in how the two files relate to each other.
 *
 * Deliberately non-blocking (see PAIRS below): a static regex scan over PHP
 * source can't see conditionally-rendered or PHP-generated ids, so false
 * positives are possible. Run with --strict to make it a real CI gate once
 * you've watched it run clean for a while.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const STRICT = process.argv.includes('--strict');

// Known page/script pairings. Add an entry whenever a new page gets its own
// dedicated bundle — this only checks pairs listed here, not the whole repo,
// to keep false positives low.
const PAIRS = [
    { php: 'onboarding.php', js: ['assets/js/onboarding-bundle.js'] },
    { php: 'onboarding-new.php', js: ['assets/js/onboarding-bundle.js'] },
    { php: 'tutor_mysql.php', js: ['assets/js/tutor_mysql.js', 'assets/js/tm-widgets.js'] },
];

function readIfExists(relPath) {
    const full = path.join(ROOT, relPath);
    if (!fs.existsSync(full)) return null;
    return fs.readFileSync(full, 'utf8');
}

function extractStaticIds(phpSource) {
    const ids = new Set();
    const re = /\bid\s*=\s*["']([A-Za-z0-9_-]+)["']/g;
    let m;
    while ((m = re.exec(phpSource))) ids.add(m[1]);
    return ids;
}

function extractGetElementByIdCalls(jsSource) {
    // Only resolves simple string-literal arguments; anything dynamic
    // (variables, template literals with ${}) is intentionally skipped
    // rather than guessed at, to avoid false positives.
    const resolved = [];
    const re = /getElementById\(\s*(['"])([A-Za-z0-9_-]+)\1\s*\)/g;
    let m;
    while ((m = re.exec(jsSource))) resolved.push(m[2]);
    return resolved;
}

let warnings = 0;
let checkedPairs = 0;

for (const pair of PAIRS) {
    const phpSource = readIfExists(pair.php);
    if (phpSource === null) {
        console.log(`[skip] ${pair.php} not found`);
        continue;
    }

    const phpIds = extractStaticIds(phpSource);
    checkedPairs++;

    for (const jsRel of pair.js) {
        const jsSource = readIfExists(jsRel);
        if (jsSource === null) {
            console.log(`[skip] ${jsRel} not found (paired with ${pair.php})`);
            continue;
        }

        const referenced = extractGetElementByIdCalls(jsSource);
        const missing = [...new Set(referenced)].filter(id => !phpIds.has(id));

        if (missing.length > 0) {
            warnings += missing.length;
            console.warn(`\n⚠️  ${jsRel} references getElementById() ids not found as literal id="..." in ${pair.php}:`);
            missing.forEach(id => console.warn(`     - "${id}"`));
            console.warn('    (This is a static text scan — a false positive is possible if the id is');
            console.warn('     rendered conditionally by PHP, or created dynamically in JS. Verify before assuming a bug.)');
        }
    }
}

console.log(`\nChecked ${checkedPairs} page/script pairing(s). ${warnings} potential mismatch(es) found.`);

if (warnings > 0 && STRICT) {
    console.error('\n--strict was set: failing the build.');
    process.exit(1);
}
process.exit(0);
