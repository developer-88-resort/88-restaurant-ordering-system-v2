import { useTranslation } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';

const roleLabelKeys = {
    superadmin: 'Superadmin',
    admin: 'Admin',
    staff: 'Staff',
};

export default function AccountInfo() {
    const t = useTranslation();
    const user = usePage().props.auth.user;

    const memberSince = new Date(user.created_at).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <section className="bg-white border border-[#E5DDD0] rounded-xl p-6">
            <h3 className="text-base font-semibold text-gray-900 mb-4">{t('Account')}</h3>

            <dl className="space-y-3 text-sm">
                <div className="flex items-center justify-between">
                    <dt className="text-gray-500">{t('Role')}</dt>
                    <dd>
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide bg-[#F3E1DC] text-[#8A3330]">
                            {t(roleLabelKeys[user.role] ?? user.role)}
                        </span>
                    </dd>
                </div>
                <div className="flex items-center justify-between">
                    <dt className="text-gray-500">{t('Member since')}</dt>
                    <dd className="text-gray-800 font-medium">{memberSince}</dd>
                </div>
                <div className="flex items-center justify-between">
                    <dt className="text-gray-500">{t('Email status')}</dt>
                    <dd>
                        {user.email_verified_at ? (
                            <span className="inline-flex items-center gap-1 text-green-700 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-3.5 w-3.5">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {t('Verified')}
                            </span>
                        ) : (
                            <span className="text-amber-600 font-medium">{t('Unverified')}</span>
                        )}
                    </dd>
                </div>
            </dl>
        </section>
    );
}
