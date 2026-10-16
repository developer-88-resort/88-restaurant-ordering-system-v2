<?php

namespace App\Enums;

/**
 * The stage of one update on an incident's timeline ("Investigating -
 * We are looking into …"), as on status.claude.com. Resolved is added by
 * switching Maintenance Mode off.
 */
enum IncidentStatus: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case InProgress = 'in_progress';
    case Update = 'update';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Investigating => __('Investigating'),
            self::Identified => __('Identified'),
            self::InProgress => __('In progress'),
            self::Update => __('Update'),
            self::Monitoring => __('Monitoring'),
            self::Resolved => __('Resolved'),
        };
    }

    /** What a Superadmin can post while the incident is open. */
    public static function postable(): array
    {
        return [self::Investigating, self::Identified, self::InProgress, self::Update, self::Monitoring];
    }
}
