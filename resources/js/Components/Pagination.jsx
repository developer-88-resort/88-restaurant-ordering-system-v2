import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <div className="mt-6 flex flex-wrap items-center justify-center gap-1">
            {links.map((link, index) => {
                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className="px-3 py-1.5 text-sm rounded-lg text-gray-300 select-none"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`px-3 py-1.5 text-sm rounded-lg transition ${
                            link.active ? 'bg-[#8A3330] text-white font-semibold' : 'text-gray-600 hover:bg-[#FAF6EE]'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                );
            })}
        </div>
    );
}
