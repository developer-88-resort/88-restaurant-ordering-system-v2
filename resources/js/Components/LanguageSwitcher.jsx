import Dropdown from '@/Components/Dropdown';
import { usePage } from '@inertiajs/react';

export default function LanguageSwitcher() {
    const { locale, availableLocales } = usePage().props;

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#D9CCBA] text-sm font-medium text-gray-700 hover:bg-gray-50 bg-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-4 w-4 shrink-0">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" />
                    </svg>
                    <span>{availableLocales?.[locale] ?? 'English'}</span>
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content>
                {Object.entries(availableLocales ?? {}).map(([code, label]) => (
                    // data-turbo="false": this redirects back to whatever page it was submitted from
                    // (redirect()->back()) — on an Inertia page, Turbo (loaded app-wide) would otherwise
                    // try to XHR+morph that redirect and leave a blank page, the same bug as logout.
                    <form key={code} method="POST" action={route('locale.update')} data-turbo="false">
                        <input type="hidden" name="_token" value={usePage().props.csrf_token} />
                        <input type="hidden" name="locale" value={code} />
                        <button
                            type="submit"
                            className={`w-full flex items-center justify-between px-4 py-2 text-sm text-left ${
                                locale === code ? 'font-semibold text-[#8A3330] bg-[#FAF6EE]' : 'text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            {label}
                            {locale === code && (
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            )}
                        </button>
                    </form>
                ))}
            </Dropdown.Content>
        </Dropdown>
    );
}
