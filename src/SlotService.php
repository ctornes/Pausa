<?php

declare(strict_types=1);

namespace App;

/** Derives the day's slots from the settings; slots are never stored. */
final class SlotService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /** @return list<array{start: string, end: string, slots: list<array{start: string, end: string}>}> */
    public function windows(): array
    {
        $length = $this->settings->slotMinutes();
        $windows = [];

        foreach ($this->settings->windows() as [$from, $to]) {
            $slots = [];
            for ($t = $from; $t + $length <= $to; $t += $length) {
                $slots[] = ['start' => Time::format($t), 'end' => Time::format($t + $length)];
            }
            $windows[] = ['start' => Time::format($from), 'end' => Time::format($to), 'slots' => $slots];
        }

        return $windows;
    }

    public function count(): int
    {
        return array_sum(array_map(fn (array $w) => count($w['slots']), $this->windows()));
    }

    public function exists(string $start): bool
    {
        if (!Time::isValid($start)) {
            return false;
        }
        foreach ($this->windows() as $window) {
            foreach ($window['slots'] as $slot) {
                if ($slot['start'] === $start) {
                    return true;
                }
            }
        }

        return false;
    }

    public function endOf(string $start): string
    {
        return Time::format(Time::toMinutes($start) + $this->settings->slotMinutes());
    }
}
