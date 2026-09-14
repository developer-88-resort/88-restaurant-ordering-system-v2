<?php

namespace App\Support;

use Stevebauman\Purify\Facades\Purify;

/**
 * Sanitizes the HTML a menu item's rich-text description editor produces
 * before it's stored — this is the ONLY thing standing between an admin's
 * (or a compromised admin session's) input and the public, unauthenticated
 * customer menu that renders it via dangerouslySetInnerHTML. The allowed
 * tag/attribute list below must stay in lockstep with what
 * resources/js/Components/RichTextEditor.jsx's toolbar can actually produce
 * — nothing more.
 */
class MenuDescriptionPurifier
{
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $cleaned = trim(Purify::config([
            'HTML.Allowed' => 'p,br,strong,em,u,s,h1,h2,h3,ul,ol,li,a[href|target|rel],span[style],mark[style]',
            'CSS.AllowedProperties' => 'color,background-color,font-family,font-size',
            'Attr.AllowedRel' => ['noopener', 'noreferrer', 'nofollow'],
            'HTML.TargetBlank' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
        ])->clean($html));

        // An editor that was emptied out (backspaced to nothing) still emits
        // "<p></p>" — collapse that back to null instead of persisting a
        // technically-non-empty-but-visually-blank HTML fragment.
        return trim(strip_tags($cleaned)) === '' ? null : $cleaned;
    }
}
