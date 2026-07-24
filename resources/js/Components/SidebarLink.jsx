import { Link } from '@inertiajs/react';

export default function SidebarLink({ href, active, icon, badge, collapsed, onClick, inertia = false, children }) {
    const showBadge = badge != null && badge > 0;
    const Tag = inertia ? Link : 'a';

    return (
        <Tag
            href={href}
            onClick={onClick}
            title={typeof children === 'string' ? children : undefined}
            {...(inertia ? { 'data-turbo': 'false' } : {})}
            className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150 ${
                collapsed ? 'lg:justify-center lg:px-0' : ''
            } ${
                active
                    ? 'bg-[#8A3330] text-white shadow-sm shadow-[#8A3330]/20'
                    : 'text-gray-600 hover:bg-[#F3E1DC]/70 hover:text-[#8A3330]'
            }`}
        >
            <span className={`relative shrink-0 h-5 w-5 [&>svg]:h-5 [&>svg]:w-5 ${active ? 'text-white' : 'text-gray-400'}`}>
                {icon}
                {showBadge && (
                    <span
                        className={`absolute -top-1.5 -right-1.5 h-4 min-w-[1rem] px-1 items-center justify-center rounded-full bg-red-600 text-white text-[10px] font-bold leading-none ${
                            collapsed ? 'hidden lg:flex' : 'hidden'
                        }`}
                    >
                        {badge}
                    </span>
                )}
            </span>
            <span className={`truncate flex-1 ${collapsed ? 'lg:hidden' : ''}`}>{children}</span>
            {showBadge && (
                <span
                    className={`shrink-0 h-5 min-w-[1.25rem] px-1.5 flex items-center justify-center rounded-full bg-red-600 text-white text-[11px] font-bold leading-none ${
                        collapsed ? 'lg:hidden' : ''
                    }`}
                >
                    {badge}
                </span>
            )}
        </Tag>
    );
}
