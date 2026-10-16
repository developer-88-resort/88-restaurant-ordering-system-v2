import LanguageSwitcher from '@/Components/LanguageSwitcher';
import Toast from '@/Components/Toast';

export default function GuestLayout({ children, modern = false }) {
    return (
        <>
            <Toast />

            <div className="fixed top-4 right-4 z-50">
                <LanguageSwitcher />
            </div>

            <div className={modern ? "min-h-screen flex items-center justify-center bg-slate-50 px-4 pb-8 pt-20 sm:px-8" : "min-h-screen flex items-center justify-center bg-[#F7F0E3] px-6"}>
                {children}
            </div>
        </>
    );
}
