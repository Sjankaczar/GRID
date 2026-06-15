<?php

/**
 * Sanitasi output untuk mencegah XSS.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ke URL tertentu dan hentikan eksekusi.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Set flash message ke session.
 *
 * @param string $type  'success' | 'error' | 'warning' | 'info'
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil dan hapus flash message dari session.
 */
function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Render flash message sebagai HTML alert Bootstrap.
 */
function render_flash(): void {
    $flash = get_flash();
    if (!$flash) return;

    $type_map = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];
    $class = $type_map[$flash['type']] ?? 'alert-secondary';

    echo '<div class="alert ' . $class . ' alert-dismissible" role="alert">';
    echo e($flash['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}

/**
 * Format ukuran file dari KB ke string.
 */
function format_filesize(int $kb): string {
    if ($kb >= 1024) {
        return number_format($kb / 1024, 2) . ' MB';
    }
    return $kb . ' KB';
}

/**
 * Format tanggal dari format MySQL ke format lokal Indonesia.
 */
function format_tanggal(string $date): string {
    if (empty($date)) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Potong teks panjang dan tambahkan ellipsis.
 */
function truncate(string $text, int $limit = 80): string {
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '...';
}

/**
 * Kembalikan class badge Bootstrap berdasarkan status aset.
 */
function badge_status_aset(string $status): string {
    return match($status) {
        'Approved' => 'bg-success',
        'Rejected' => 'bg-danger',
        default    => 'bg-warning text-dark', // Pending
    };
}

/**
 * Kembalikan class badge Bootstrap berdasarkan prioritas tugas.
 */
function badge_prioritas(string $prioritas): string {
    return match($prioritas) {
        'Critical' => 'bg-danger',
        'High'     => 'bg-warning text-dark',
        'Medium'   => 'bg-info text-dark',
        default    => 'bg-secondary', // Low
    };
}

/**
 * Kembalikan class badge Bootstrap berdasarkan status proyek.
 */
function badge_status_proyek(string $status): string {
    return match($status) {
        'Development' => 'bg-primary',
        'Testing'     => 'bg-info text-dark',
        'Released'    => 'bg-success',
        'On Hold'     => 'bg-secondary',
        default       => 'bg-warning text-dark', // Planning
    };
}

/**
 * Rate limiting sederhana berbasis session.
 *
 * @param string $action  Nama aksi
 * @param int    $limit   Maksimum percobaan
 * @param int    $window  Jangka waktu dalam detik
 */
function rate_limit_check(string $action, int $limit = 5, int $window = 60): bool {
    $key = 'rate_' . $action;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => $now];
    }

    // Reset jika window sudah lewat
    if (($now - $_SESSION[$key]['start']) > $window) {
        $_SESSION[$key] = ['count' => 0, 'start' => $now];
    }

    $_SESSION[$key]['count']++;

    return $_SESSION[$key]['count'] <= $limit;
}
