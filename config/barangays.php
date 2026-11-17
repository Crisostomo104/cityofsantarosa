<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  CENTRALIZED BARANGAY LIST — Santa Rosa, Laguna
 *  Single source of truth for all barangay dropdowns.
 *  To add/remove a barangay, edit only this file.
 * ─────────────────────────────────────────────────────────────
 */

function get_barangays(): array
{
    return [
        'APLAYA',
        'BALIBAGO',
        'CAINGIN',
        'DILA',
        'DITA',
        'DON JOSE',
        'IBABA',
        'KANLURAN',
        'LABAS',
        'MACABLING',
        'MALITLIT',
        'MARKET AREA',
        'MALUSAK',
        'POOC',
        'PULONG SANTA CRUZ',
        'SANTO DOMINGO',
        'SINALHAN',
        'TAGAPO',
    ];
}

/**
 * Render barangay <option> tags for a <select> dropdown.
 * @param string|null $selected  The currently selected value (for edit forms)
 */
function barangay_options(?string $selected = null): string
{
    $html = '<option value="">-- Select Barangay --</option>';
    foreach (get_barangays() as $brgy) {
        $label = ucwords(strtolower($brgy));
        $sel = ($selected === $brgy) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($brgy) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}
