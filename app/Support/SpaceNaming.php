<?php

namespace App\Support;

use App\Models\Space;
use App\Models\SpaceCategory;
use Illuminate\Support\Str;

/**
 * Every space in a category shares one naming prefix plus a number —
 * "KUBO 21", "KR 16", "KOLD-R 21", "MN-T 11" — so a table's name alone says
 * where it is. The prefix is the one the category's first space already
 * uses (a category's own name and its tables' prefix can differ: tables in
 * "Korean-OLDTB" are "KOLD-R n"); only an empty category gets to choose one,
 * and never a prefix another category already uses. Free-typed prefixes had
 * produced "Korean resto -R1 1" inside KUBO and "KUBO OT 18 1" inside KR,
 * which muddled both the floor plan and the reports.
 */
class SpaceNaming
{
    /** "<prefix> <number>" → [prefix, number], or null. */
    public static function parse(string $name): ?array
    {
        return preg_match('/^(.*\S)\s+(\d{1,4})$/u', trim($name), $m)
            ? [$m[1], (int) $m[2]]
            : null;
    }

    /**
     * The prefix the category's spaces already use: that of its earliest
     * numbered space. Null for a category with no spaces yet.
     */
    public static function prefix(SpaceCategory $category, ?int $ignoreSpaceId = null): ?string
    {
        return Space::where('category_id', $category->id)
            ->when($ignoreSpaceId, fn ($query) => $query->whereKeyNot($ignoreSpaceId))
            ->orderBy('id')
            ->pluck('name')
            ->map(fn (string $name) => self::parse($name)[0] ?? null)
            ->filter()
            ->first();
    }

    public static function samePrefix(string $a, string $b): bool
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($a))) === Str::lower(preg_replace('/\s+/u', ' ', trim($b)));
    }

    /** Another category whose spaces already go by this prefix, if any. */
    public static function prefixOwner(string $prefix, SpaceCategory $except): ?SpaceCategory
    {
        return SpaceCategory::whereKeyNot($except->id)->get()
            ->first(fn (SpaceCategory $other) => ($owned = self::prefix($other)) !== null && self::samePrefix($owned, $prefix));
    }

    public static function format(string $prefix, int $number): string
    {
        return trim($prefix).' '.$number;
    }

    /** Whether this number is already used under the prefix in the category. */
    public static function numberTaken(SpaceCategory $category, string $prefix, int $number, ?int $ignoreSpaceId = null): bool
    {
        return Space::where('category_id', $category->id)
            ->when($ignoreSpaceId, fn ($query) => $query->whereKeyNot($ignoreSpaceId))
            ->pluck('name')
            ->contains(function (string $name) use ($prefix, $number) {
                $parsed = self::parse($name);

                return $parsed && $parsed[1] === $number && self::samePrefix($parsed[0], $prefix);
            });
    }

    /** The number right after the highest one used under the category's prefix. */
    public static function nextNumber(SpaceCategory $category): int
    {
        $prefix = self::prefix($category);

        if ($prefix === null) {
            return 1;
        }

        return Space::where('category_id', $category->id)
            ->pluck('name')
            ->map(fn (string $name) => self::parse($name))
            ->filter(fn ($parsed) => $parsed && self::samePrefix($parsed[0], $prefix))
            ->max(fn ($parsed) => $parsed[1]) + 1;
    }

    /**
     * Why $name can't be used for a space in $category, or null if it can.
     * On success $resolved is set to [prefix to save under, number].
     */
    public static function problem(string $name, SpaceCategory $category, ?int $ignoreSpaceId = null, ?array &$resolved = null): ?string
    {
        $parsed = self::parse($name);
        $prefix = self::prefix($category, $ignoreSpaceId);

        if (! $parsed) {
            return $prefix
                ? __('Space names in :category must be ":prefix" followed by a number, e.g. ":example".', ['category' => $category->name, 'prefix' => $prefix, 'example' => self::format($prefix, self::nextNumber($category))])
                : __('A space name needs a number at the end, e.g. ":example".', ['example' => 'Table 1']);
        }

        [$typedPrefix, $number] = $parsed;

        if ($prefix !== null && ! self::samePrefix($typedPrefix, $prefix)) {
            return __('Space names in :category must be ":prefix" followed by a number, e.g. ":example".', ['category' => $category->name, 'prefix' => $prefix, 'example' => self::format($prefix, self::nextNumber($category))]);
        }

        if ($prefix === null && ($owner = self::prefixOwner($typedPrefix, $category))) {
            return __('":prefix" is already used by :category. Pick a different name for this category\'s spaces.', ['prefix' => $typedPrefix, 'category' => $owner->name]);
        }

        $savedPrefix = $prefix ?? $typedPrefix;

        if (self::numberTaken($category, $savedPrefix, $number, $ignoreSpaceId)) {
            return __(':name already exists.', ['name' => self::format($savedPrefix, $number)]);
        }

        $resolved = [$savedPrefix, $number];

        return null;
    }
}
