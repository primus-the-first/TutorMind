<?php
function render_illustration(string $name): string {
    $path = __DIR__ . '/../assets/illustrations/' . $name . '.svg';
    if (!file_exists($path)) {
        return '';
    }
    return file_get_contents($path);
}
