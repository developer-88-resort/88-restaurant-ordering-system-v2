<?php

namespace App\Enums;

/**
 * Which part of the resort an Admin/Staff account works in. Restaurant is
 * the ordering system (Korean Restaurant, Ihawan, Minibar); Services
 * (Massage, Souvenir) gets its own pages and never sees the restaurant's.
 * A Superadmin has no department and sees everything. Reports stay
 * combined for every Admin.
 */
enum Department: string
{
    case Restaurant = 'restaurant';
    case Services = 'services';

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => __('Restaurant'),
            self::Services => __('Services'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Restaurant => __('Korean Restaurant, Ihawan and Minibar: orders, kitchen, menu and spaces.'),
            self::Services => __('Massage and Souvenir. No access to the restaurant pages.'),
        };
    }
}
