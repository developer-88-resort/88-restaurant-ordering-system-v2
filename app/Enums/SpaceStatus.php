<?php

namespace App\Enums;

enum SpaceStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Maintenance = 'maintenance';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Occupied => __('Occupied'),
            self::Reserved => __('Reserved'),
            self::Maintenance => __('Maintenance'),
            self::Disabled => __('Disabled'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Available => 'bg-green-600 text-white',
            self::Occupied => 'bg-orange-500 text-white',
            self::Reserved => 'bg-blue-600 text-white',
            self::Maintenance => 'bg-red-600 text-white',
            self::Disabled => 'bg-gray-500 text-white',
        };
    }

    /**
     * Softer, easier-on-the-eyes styling for dense grids (e.g. the New
     * Order location picker) — white card with a colored left accent
     * border and matching status text, instead of a full solid fill.
     */
    public function pickerAccentClasses(): string
    {
        return match ($this) {
            self::Available => 'border-l-green-500 text-green-700',
            self::Occupied => 'border-l-orange-500 text-orange-700',
            self::Reserved => 'border-l-blue-500 text-blue-700',
            self::Maintenance => 'border-l-red-500 text-red-700',
            self::Disabled => 'border-l-gray-500 text-gray-700',
        };
    }

    /**
     * Solid color for the accent capsule peeking behind the white status
     * pill on the Spaces page.
     */
    public function capsuleAccentClass(): string
    {
        return match ($this) {
            self::Available => 'bg-green-500',
            self::Occupied => 'bg-orange-500',
            self::Reserved => 'bg-blue-500',
            self::Maintenance => 'bg-red-500',
            self::Disabled => 'bg-gray-500',
        };
    }

    /**
     * Same colors as capsuleAccentClass(), as literal hex — SVG presentation
     * attributes (fill/stroke) don't resolve Tailwind utility classes, so the
     * floor plan's table shapes need actual color values instead.
     */
    public function hexColor(): string
    {
        return match ($this) {
            self::Available => '#22c55e',
            self::Occupied => '#f97316',
            self::Reserved => '#3b82f6',
            self::Maintenance => '#ef4444',
            self::Disabled => '#6b7280',
        };
    }
}
