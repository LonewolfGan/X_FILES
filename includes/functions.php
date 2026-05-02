<?php
/**
 * XFILES — Fonctions utilitaires partagées
 */

// --- TYPES DE DOCUMENTS ---

function getDocTypes(): array
{
    return [
        'cours'  => ['label' => 'Cours',    'icon' => 'fa-book'],
        'td'     => ['label' => 'TD',       'icon' => 'fa-list-check'],
        'tp'     => ['label' => 'TP',       'icon' => 'fa-flask'],
        'examen' => ['label' => 'Examen',   'icon' => 'fa-file-circle-question'],
        'resume' => ['label' => 'Résumé',   'icon' => 'fa-note-sticky'],
    ];
}

function typeLabel(string $type): string
{
    $types = getDocTypes();
    return $types[$type]['label'] ?? ucfirst($type);
}

function typeIcon(string $type): string
{
    $types = getDocTypes();
    return $types[$type]['icon'] ?? 'fa-file-lines';
}

function typeClass(string $type): string
{
    return 'doc-type-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
}

// --- URL BUILDING ---

/**
 * Construit une URL vers dashboard.php avec les paramètres donnés
 */
function buildUrl(array $params): string
{
    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });
    return BASE_URL . 'pages/dashboard.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

/**
 * Toggle un type dans la liste des types sélectionnés et retourne l'URL
 */
function toggleTypeUrl(array $currentTypes, string $type, array $urlParams): string
{
    if (in_array($type, $currentTypes, true)) {
        $newTypes = array_values(array_filter($currentTypes, fn($t) => $t !== $type));
    } else {
        $newTypes = array_merge($currentTypes, [$type]);
    }
    $params = array_merge($urlParams, [
        'types' => !empty($newTypes) ? implode(',', $newTypes) : null,
        'page'  => null,
    ]);
    return buildUrl($params);
}

/**
 * Toggle une valeur dans un tableau de filtres et retourne l'URL
 */
function toggleValueUrl(array $current, string $value, string $key, array $urlParams): string
{
    if (in_array($value, $current, true)) {
        $next = array_values(array_filter($current, fn($v) => $v !== $value));
    } else {
        $next = array_merge($current, [$value]);
    }
    $params = array_merge($urlParams, [
        $key   => !empty($next) ? implode(',', $next) : null,
        'page' => null,
    ]);
    return buildUrl($params);
}

// --- VALIDATION ---

/**
 * Nettoie une chaîne pour l'affichage HTML
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

