export default function SidebarGroup({ label, collapsed, children }) {
    return (
        <div>
            <div className={`mb-2 flex items-center px-3 ${collapsed ? 'lg:justify-center lg:px-0' : ''}`}>
                <p className={`truncate text-xs font-semibold uppercase tracking-wider text-gray-400 ${collapsed ? 'lg:hidden' : ''}`}>
                    {label}
                </p>
                <div className={`w-6 border-t border-[#E5DDD0] ${collapsed ? 'hidden lg:block' : 'hidden'}`} />
            </div>
            <div className="space-y-1">{children}</div>
        </div>
    );
}
