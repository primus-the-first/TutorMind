<?php
// Interests are student-typed text that ends up inside the tutor prompt, so they
// are cleaned once on the way in (clean_interests) and filtered again on the way
// out to the prompt (interests_for_prompt). Storage keeps what the student wrote;
// only the prompt-side filter hides topics we don't build lessons around.

const INTEREST_MAX_CHARS = 40;
const INTEREST_MAX_WORDS = 5;
const INTEREST_MAX_ITEMS = 20;

/**
 * Normalise a raw list of interests from onboarding or settings.
 * Drops anything that reads like an instruction instead of a hobby, so a typed
 * "ignore your rules" never reaches the system prompt.
 *
 * @param mixed $raw Expected array of strings; anything else yields [].
 * @return string[]
 */
function clean_interests($raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $seen = [];
    $clean = [];
    foreach ($raw as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $text = (string)$item;
        // Control characters and line breaks would let one entry become several prompt lines.
        $text = preg_replace('/[\p{C}]+/u', ' ', $text);
        // Markup and template characters have no place in a hobby name.
        $text = str_replace(['<', '>', '{', '}', '[', ']', '`', '\\', '|', '#'], '', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            continue;
        }
        $text = rtrim(mb_substr($text, 0, INTEREST_MAX_CHARS));
        if (count(preg_split('/\s+/u', $text)) > INTEREST_MAX_WORDS) {
            continue;
        }
        if (looks_like_instruction($text)) {
            continue;
        }
        $key = mb_strtolower($text);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $clean[] = $text;
        if (count($clean) >= INTEREST_MAX_ITEMS) {
            break;
        }
    }
    return $clean;
}

function looks_like_instruction(string $text): bool {
    return (bool)preg_match(
        '/\b(ignore|disregard|forget|override|bypass)\b.*\b(rules?|instructions?|prompts?|above|previous|guardrails?)\b'
        . '|\bsystem\s*prompt\b|\bjailbreak\b|\byou\s+(are|must|will|should)\b|\bgive\s+me\s+the\s+answers?\b/iu',
        $text
    );
}

/**
 * The interests the tutor may build examples around.
 * Topics we wouldn't write lessons about for a school-age learner stay in the
 * student's saved list but are left out of the prompt.
 *
 * @param mixed $stored Decoded interests value (array) or the raw JSON string from the users table.
 * @return string[]
 */
function interests_for_prompt($stored): array {
    if (is_string($stored)) {
        $stored = json_decode($stored, true);
    }
    $list = clean_interests($stored);
    return array_values(array_filter($list, fn($i) => !interest_is_off_limits($i)));
}

function interest_is_off_limits(string $interest): bool {
    return (bool)preg_match(
        '/\b(bet|bets|betting|gambl\w*|casino|poker|sportsbook|alcohol|drinking|beer|vodka|weed|cannabis|vap(e|es|ing)|porn\w*|nsfw|onlyfans)\b/iu',
        $interest
    );
}
