<?php

namespace App\Enums;

/**
 * How bad an incident on the maintenance page's status section is. It
 * colours the incident's heading and the uptime bars for its days, the way
 * status.claude.com does.
 */
enum IncidentImpact: string
{
    case Maintenance = 'maintenance';
    case Minor = 'minor';
    case Major = 'major';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => __('Maintenance'),
            self::Minor => __('Minor issue'),
            self::Major => __('Major outage'),
        };
    }

    /** Heading and bar colour. */
    public function color(): string
    {
        return match ($this) {
            self::Maintenance => '#3B82F6',
            self::Minor => '#F59E0B',
            self::Major => '#E11D48',
        };
    }

    /** Higher is worse; a day's bar takes its worst incident's colour. */
    public function severity(): int
    {
        return match ($this) {
            self::Maintenance => 1,
            self::Minor => 2,
            self::Major => 3,
        };
    }
}
