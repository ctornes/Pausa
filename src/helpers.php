<?php

declare(strict_types=1);

use App\BookingService;
use App\Db;
use App\FixedSlotService;
use App\PhotoStore;
use App\PushService;
use App\ReminderLog;
use App\ReminderService;
use App\Settings;
use App\SlotService;
use App\UserRepository;

function config(string $key): mixed
{
    static $config = null;
    $config ??= require APP_ROOT . '/config.php';

    return $config[$key];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $path = '/'): string
{
    return config('base_url') . $path;
}

function asset(string $file): string
{
    $version = @filemtime(APP_ROOT . '/public/assets/' . $file) ?: 0;

    return url('/assets/' . $file) . '?v=' . $version;
}

function view(string $__view, array $__data = []): string
{
    extract($__data);
    ob_start();
    require APP_ROOT . '/views/' . $__view . '.php';

    return (string) ob_get_clean();
}

/** Renders a view inside the page layout and stops the request. */
function page(string $view, string $title, array $data = []): never
{
    $data['title'] = $title;
    echo view('layout', $data + ['content' => view($view, $data)]);
    exit;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** True for requests sent by app.js, which expect JSON or HTML fragments instead of redirects. */
function is_fetch(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Stores a one-shot message for the next page, or pulls the stored one when called without arguments. */
function flash(?string $message = null, bool $ok = true): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'ok' => $ok];

        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

    return mb_strtoupper(implode('', $letters));
}

/** A round avatar: the person's photo when they have one, otherwise their initials. */
function avatar(int $userId, string $name, ?int $photoVersion, string $size = ''): string
{
    $class = trim("avatar $size") . ' tone-' . avatar_tone($userId);
    $inner = $photoVersion !== null
        ? '<img src="' . e(url("/photo.php?u=$userId&v=$photoVersion")) . '" alt="" loading="lazy">'
        : e(initials($name));

    return "<span class=\"$class\" aria-hidden=\"true\">$inner</span>";
}

/** Stable avatar colour (1–6) per person. */
function avatar_tone(int $userId): int
{
    return $userId % 6 + 1;
}

function italian_date(DateTimeInterface $date): string
{
    $days = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    $months = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio',
        'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    return $days[(int) $date->format('w')] . ' ' . $date->format('j') . ' ' . $months[(int) $date->format('n') - 1];
}

/** Joins names the Italian way: "Anna", "Anna e Luca", "Anna, Luca e Sara". */
function join_names(array $names): string
{
    if (count($names) < 2) {
        return implode('', $names);
    }
    $last = array_pop($names);

    return implode(', ', $names) . ' e ' . $last;
}

function settings(): Settings
{
    static $settings = null;

    return $settings ??= new Settings(Db::get());
}

function users(): UserRepository
{
    static $users = null;

    return $users ??= new UserRepository(Db::get());
}

function photos(): PhotoStore
{
    static $photos = null;

    // Photos live next to the database in use, so a test database gets its own folder.
    return $photos ??= new PhotoStore(Db::get(), dirname(config('db_path')) . '/photos');
}

function fixed_slots(): FixedSlotService
{
    static $fixed = null;

    return $fixed ??= new FixedSlotService(Db::get(), settings(), new SlotService(settings()));
}

function bookings(): BookingService
{
    static $bookings = null;

    return $bookings ??= new BookingService(Db::get(), settings(), new SlotService(settings()), fixed_slots());
}

function push(): PushService
{
    static $push = null;

    return $push ??= new PushService(Db::get(), dirname(config('db_path')) . '/vapid.json', config('push_subject'), reminder_log());
}

function reminders(): ReminderService
{
    static $reminders = null;

    return $reminders ??= new ReminderService(Db::get(), push(), fixed_slots(), new SlotService(settings()));
}

function reminder_log(): ReminderLog
{
    static $log = null;
    $dir = dirname(config('db_path'));

    return $log ??= new ReminderLog("$dir/reminders.log", "$dir/reminders.last");
}
