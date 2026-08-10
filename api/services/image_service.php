<?php
/**
 * Image Service
 * Fetches relevant educational images from free public APIs (PubChem, Wikipedia).
 * No API keys required.
 */

/**
 * Route an image fetch to the best source for the given subject.
 * Returns a direct image URL, or null if nothing useful was found.
 */
function fetchRelevantImage(string $query, string $subject = 'general'): ?string
{
    $subject = strtolower(trim($subject));

    // Chemistry: PubChem has exact molecular structure images
    if ($subject === 'chemistry') {
        $url = fetchPubChemImage($query);
        if ($url) return $url;
    }

    // Biology, physics, history, geography, programming (data structures), general: Wikipedia
    $url = fetchWikipediaImage($query);
    if ($url) return $url;

    // Chemistry fallback: try Wikipedia if PubChem didn't find it
    if ($subject === 'chemistry') {
        return fetchWikipediaImage($query . ' chemistry');
    }

    return null;
}

/**
 * Look up a chemical compound on PubChem and return its structure image URL.
 * PubChem's REST API is completely free with no key required.
 */
function fetchPubChemImage(string $compound): ?string
{
    $imageUrl = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/'
        . urlencode($compound) . '/PNG';

    // HEAD request to verify the compound exists before returning the URL
    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'TutorMind/1.0',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code === 200 ? $imageUrl : null;
}

/**
 * Fetch the main thumbnail image for a Wikipedia article.
 * Returns the thumbnail URL, or null if no image was found.
 */
function fetchWikipediaImage(string $query): ?string
{
    $apiUrl = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'titles'      => $query,
        'prop'        => 'pageimages',
        'format'      => 'json',
        'pithumbsize' => 500,
        'pilimit'     => 1,
        'redirects'   => 1,
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'TutorMind/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$response) return null;

    $data  = json_decode($response, true);
    $pages = $data['query']['pages'] ?? [];

    foreach ($pages as $page) {
        // -1 means the article doesn't exist
        if (($page['pageid'] ?? -1) === -1) continue;
        $thumb = $page['thumbnail']['source'] ?? null;
        if ($thumb) return $thumb;
    }

    return null;
}

/**
 * Scan formatted HTML for [FETCH_IMAGE: query|subject] markers left by the
 * response formatter, fetch each image, and replace markers with <figure> HTML.
 * Unfulfilled markers are silently removed.
 * At most 5 images are resolved per response; duplicate query|subject keys
 * reuse the cached URL from the first fetch.
 */
function resolveImageMarkers(string $html): string
{
    $resolved = 0;
    $maxImages = 5;
    $cache = [];

    return (string) preg_replace_callback(
        '/\[FETCH_IMAGE:\s*([^|\]]+?)(?:\|([^\]]+))?\]/i',
        function (array $matches) use (&$resolved, $maxImages, &$cache): string {
            if ($resolved >= $maxImages) return '';

            $query   = trim($matches[1]);
            $subject = isset($matches[2]) ? trim($matches[2]) : 'general';
            $cacheKey = $query . '|' . $subject;

            if (array_key_exists($cacheKey, $cache)) {
                $imageUrl = $cache[$cacheKey];
            } else {
                $imageUrl = fetchRelevantImage($query, $subject);
                $cache[$cacheKey] = $imageUrl;
            }

            if (!$imageUrl) return '';

            $resolved++;
            $safeAlt     = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
            $safeUrl     = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
            $safeCaption = htmlspecialchars(ucwords($query), ENT_QUOTES, 'UTF-8');

            return '<figure class="ai-fetched-image">'
                . '<img src="' . $safeUrl . '" alt="' . $safeAlt . '" loading="lazy">'
                . '<figcaption>' . $safeCaption . '</figcaption>'
                . '</figure>';
        },
        $html
    );
}
