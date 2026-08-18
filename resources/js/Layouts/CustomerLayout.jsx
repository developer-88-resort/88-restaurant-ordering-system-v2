import LanguageSwitcher from '@/Components/LanguageSwitcher';
import Toast from '@/Components/Toast';
import { Head } from '@inertiajs/react';

export default function CustomerLayout({ title, locationLabel, children }) {
    return (
        <div className="min-h-screen bg-[#F7F0E3] font-sans text-gray-900 antialiased">
            <Head title={title} />

            <Toast />

            <header className="sticky top-0 z-40 border-b border-[#E5DDD0] bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <img src="/images/logo.png" alt="88 Hot Spring Resort" className="h-9 w-9 shrink-0 rounded-full object-cover" />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-gray-900" style={{ fontFamily: "'Playfair Display', serif" }}>
                                88 Hot Spring Resort
                            </p>
                            {locationLabel && <p className="truncate text-xs font-medium text-[#8A3330]">{locationLabel}</p>}
                        </div>
                    </div>
                    <LanguageSwitcher />
                </div>
            </header>

            <main className="min-h-[calc(100vh-4rem)]">{children}</main>
        </div>
    );
}
