import { usePage } from '@inertiajs/react';

// Mirrors Laravel's __() convention: the shared `translations` prop is only
// the CURRENT locale's JSON override file — 'en' has none, since the keys
// used everywhere already ARE the English text, so a missing key just
// falls back to itself (t('Email') === 'Email' when locale is 'en').
export function useTranslation() {
    const { translations } = usePage().props;

    return (key) => translations?.[key] ?? key;
}
