<?php
/**
 * Plain-language summary of how the tutor uses the student's profile to shape lessons.
 * 
 * NOTE: This mirrors the personalization context in includes/server_mysql.php (~lines 1009-1123).
 * If the prompt branches in server_mysql.php change, keep this summary in sync.
 * DO NOT display raw prompt instructions to the client: it contains internal tutoring rules.
 */

require_once __DIR__ . '/interests.php';

/**
 * Generates an array of plain-language strings describing how the student's
 * current profile settings are used by the tutor.
 *
 * @param array $profile Profile dictionary (users table columns)
 * @return string[]
 */
function profile_summary_lines(array $profile): array {
    $lines = [];

    // 1. Region / country
    if (!empty($profile['country'])) {
        $country = trim((string)$profile['country']);
        $lines[] = "Examples use {$country}'s currency, units and spelling";
    }

    // 2. Academic level
    if (!empty($profile['education_level'])) {
        $edu = (string)$profile['education_level'];
        $edu_map = [
            'Primary'      => 'primary school',
            'Secondary'    => 'secondary school',
            'University'   => 'university level',
            'Graduate'     => 'graduate level',
            'Professional' => 'working professionals',
            'Other'        => 'your academic level',
        ];
        $target = $edu_map[$edu] ?? strtolower($edu);
        $lines[] = "Explanations are pitched for {$target}";
    }

    // 3. Field of study / subject
    if (!empty($profile['field_of_study'])) {
        $field = trim((string)$profile['field_of_study']);
        $lines[] = "Where relevant, examples draw on {$field}";
    }

    // 4. Primary language (only when set and not English)
    if (!empty($profile['primary_language']) && strcasecmp(trim((string)$profile['primary_language']), 'English') !== 0) {
        $lang = trim((string)$profile['primary_language']);
        $lines[] = "Tutor keeps in mind your main language is {$lang}";
    }

    // 5. Prior knowledge level
    if (!empty($profile['knowledge_level'])) {
        $kl = (string)$profile['knowledge_level'];
        $kl_map = [
            'beginner'     => "Explanations are pitched at a beginner level, starting from the foundations",
            'intermediate' => "Explanations are pitched at an intermediate level",
            'advanced'     => "Explanations are pitched at an advanced level, skipping the basics",
        ];
        if (isset($kl_map[$kl])) {
            $lines[] = $kl_map[$kl];
        }
    }

    // 6. Overall learning goal
    if (!empty($profile['learning_goal'])) {
        $lg = (string)$profile['learning_goal'];
        $lg_map = [
            'homework_help'    => "Guides you to your own answers step by step rather than handing over solutions",
            'exam_prep'        => "After an idea lands it follows up with an exam-style question",
            'concept_mastery'  => "Focuses on deep understanding and the why behind ideas rather than memorised steps",
            'catch_up'         => "Checks prerequisites first and fills gaps without making you feel behind",
            'get_ahead'        => "Stretches beyond the syllabus to show where topics lead next",
            'general_learning' => "Follows your curiosity and tangents rather than sticking strictly to a syllabus",
        ];
        if (isset($lg_map[$lg])) {
            $lines[] = $lg_map[$lg];
        }
    }

    // 7. Interests (via interests_for_prompt)
    $interestsList = interests_for_prompt($profile['interests'] ?? '');
    if (!empty($interestsList)) {
        $interestsStr = implode(', ', $interestsList);
        $lines[] = "Examples are built around: {$interestsStr} (general mechanics only, never specific facts)";
    }

    // 8. Response style / explanation depth
    $hasProfileData = !empty($profile['country'])
        || !empty($profile['education_level'])
        || !empty($profile['field_of_study'])
        || (!empty($profile['primary_language']) && strcasecmp(trim((string)$profile['primary_language']), 'English') !== 0)
        || !empty($profile['knowledge_level'])
        || !empty($profile['learning_goal'])
        || !empty($interestsList);

    $style = $profile['response_style'] ?? 'concise';
    if ($style === 'detailed') {
        $lines[] = "Explanations are fuller with worked examples before your turn";
    } elseif ($hasProfileData && $style === 'concise') {
        $lines[] = "Answers are kept short";
    }

    if (empty($lines)) {
        return ["Nothing yet. Add a few details above and lessons will fit you better."];
    }

    return $lines;
}
