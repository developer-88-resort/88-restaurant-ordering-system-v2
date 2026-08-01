import { useTranslation } from '@/lib/i18n';

// Mirrors the Password::defaults() rule set in AppServiceProvider::boot()
// (min 8, mixed case, numbers, symbols) — update both places together.
const RULES = [
    { label: '8 characters minimum', test: (value) => value.length >= 8 },
    { label: 'One uppercase letter', test: (value) => /[A-Z]/.test(value) },
    { label: 'One number', test: (value) => /[0-9]/.test(value) },
    { label: 'One special character !@#$%^&*', test: (value) => /[^A-Za-z0-9]/.test(value) },
];

export default function PasswordRequirements({ password }) {
    const t = useTranslation();

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 mb-4">
            {RULES.map((rule) => {
                const met = rule.test(password);
                return (
                    <div key={rule.label} className={`flex items-center gap-1.5 text-xs ${met ? 'text-green-700' : 'text-gray-400'}`}>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="h-3.5 w-3.5 shrink-0">
                            {met ? (
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            ) : (
                                <circle cx="12" cy="12" r="9" />
                            )}
                        </svg>
                        <span>{t(rule.label)}</span>
                    </div>
                );
            })}
        </div>
    );
}
