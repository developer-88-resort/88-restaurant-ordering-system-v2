import LanguageSwitcher from '@/Components/LanguageSwitcher';
import Toast from '@/Components/Toast';

export default function GuestLayout({ children }) {
    return (
        <>
            <Toast />

            <div className="fixed top-4 right-4 z-50">
                <LanguageSwitcher />
            </div>

            <div className="min-h-screen flex items-center justify-center bg-[#F7F0E3] px-6">
                {children}
            </div>
        </>
    );
}
