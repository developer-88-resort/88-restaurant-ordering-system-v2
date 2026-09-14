import CustomerLayout from '@/Layouts/CustomerLayout';
import { useTranslation } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Identify({ space, existing_guests: existingGuests, identify_url: identifyUrl }) {
    const t = useTranslation();
    const [showNameForm, setShowNameForm] = useState(existingGuests.length === 0);
    const form = useForm({ name: '', guest_token: '' });

    const confirmAs = (token) => {
        form.transform(() => ({ guest_token: token, name: '' }));
        form.post(identifyUrl, { preserveScroll: true });
    };

    const continueWithName = (e) => {
        e.preventDefault();
        form.transform((data) => ({ guest_token: '', name: data.name }));
        form.post(identifyUrl, { preserveScroll: true });
    };

    const skip = () => {
        form.transform(() => ({ guest_token: '', name: '' }));
        form.post(identifyUrl, { preserveScroll: true });
    };

    return (
        <CustomerLayout title={t('Welcome')} locationLabel={`${space.area_name} - ${space.name}`}>
            <div className="mx-auto max-w-md px-4 py-8">
                <div className="rounded-2xl border border-[#E5DDD0] bg-white p-6 text-center">
                    <h1 className="text-lg font-bold text-gray-900">
                        {t('Welcome to :space').replace(':space', space.name)}
                    </h1>
                    <p className="mt-1 text-sm text-[#8A7B6D]">
                        {t("Let's find your order — this only takes a second.")}
                    </p>
                </div>

                {existingGuests.length > 0 && !showNameForm && (
                    <div className="mt-4 rounded-2xl border border-[#E5DDD0] bg-white p-5">
                        <p className="mb-3 text-sm font-semibold text-gray-700">
                            {t('Are you one of these guests already ordering here?')}
                        </p>
                        <div className="space-y-2">
                            {existingGuests.map((guest) => (
                                <button
                                    key={guest.token}
                                    type="button"
                                    onClick={() => confirmAs(guest.token)}
                                    disabled={form.processing}
                                    className="w-full rounded-xl border border-[#E5D9CC] bg-white px-4 py-3 text-left font-semibold text-gray-900 transition hover:border-[#8A3330]/40 hover:bg-[#FAF3EE] disabled:opacity-60"
                                >
                                    {guest.label}
                                </button>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowNameForm(true)}
                            className="mt-4 text-sm font-semibold text-[#8A3330] hover:underline"
                        >
                            {t("None of these — I'm new")}
                        </button>
                    </div>
                )}

                {showNameForm && (
                    <form onSubmit={continueWithName} className="mt-4 rounded-2xl border border-[#E5DDD0] bg-white p-5">
                        <label htmlFor="guest-name" className="mb-1.5 block text-sm font-semibold text-gray-700">
                            {t("What's your name?")}
                        </label>
                        <input
                            id="guest-name"
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder={t('Optional')}
                            maxLength={60}
                            autoFocus
                            className="w-full rounded-xl border border-[#E5D9CC] px-4 py-2.5 text-sm text-gray-900 focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
                        />
                        <p className="mt-1.5 text-xs text-[#8A7B6D]">
                            {t('So we — and anyone else at this table — can tell your order apart.')}
                        </p>

                        <div className="mt-4 flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="flex-1 rounded-xl bg-[#8A3330] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#742927] disabled:opacity-60"
                            >
                                {t('Continue')}
                            </button>
                            <button
                                type="button"
                                onClick={skip}
                                disabled={form.processing}
                                className="text-sm font-semibold text-[#9A8B84] hover:text-[#463934]"
                            >
                                {t('Skip')}
                            </button>
                        </div>

                        {existingGuests.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowNameForm(false)}
                                className="mt-3 block text-xs font-semibold text-[#8A7B6D] hover:underline"
                            >
                                {t('Back')}
                            </button>
                        )}
                    </form>
                )}
            </div>
        </CustomerLayout>
    );
}
