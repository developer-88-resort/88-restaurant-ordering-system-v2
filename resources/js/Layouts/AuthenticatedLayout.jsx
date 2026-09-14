import Avatar from '@/Components/Avatar';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import SidebarGroup from '@/Components/SidebarGroup';
import SidebarLink from '@/Components/SidebarLink';
import Toast from '@/Components/Toast';
import { useTranslation } from '@/lib/i18n';
import { turboCleanup } from '@/lib/turbo-cleanup';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const icons = {
    overview: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
        </svg>
    ),
    orders: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M18.75 18.75V9.375c0-.621-.504-1.125-1.125-1.125H8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
        </svg>
    ),
    kitchen: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.601a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 18a3.75 3.75 0 00.495-7.468 5.99 5.99 0 00-1.925 3.547 5.975 5.975 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />
        </svg>
    ),
    spaces: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
        </svg>
    ),
    menu: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    ),
    reports: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
    ),
    promotions: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" />
        </svg>
    ),
    scale: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
        </svg>
    ),
    marketPrices: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
        </svg>
    ),
    cookingStyles: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0Z" />
        </svg>
    ),
    quotations: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    ),
    users: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    ),
    auditLogs: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    ),
    settings: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    ),
    chat: (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
        </svg>
    ),
};

const roleLabelKeys = {
    superadmin: 'Superadmin',
    admin: 'Admin',
    staff: 'Staff',
};

/**
 * A dropdown, not a permanently-open sub-list: with only one child item it
 * looked cluttered pinned open all the time. Starts open only when the
 * current page IS this item or one of its children, so navigating here
 * never hides where you already are.
 *
 * Children get no icon of their own (repeating the parent's icon read as
 * redundant) and no left border (a drawn rule looked dated against the
 * rest of the flat list) — just indentation and weight.
 */
function NavItem({ item, collapsed, pendingOrdersCount, unreadChatCount, t }) {
    const hasChildren = item.children.length > 0;
    const hasActiveChild = item.children.some((child) => child.active);
    const [open, setOpen] = useState(item.active || hasActiveChild);

    if (!hasChildren) {
        return (
            <SidebarLink
                navKey={item.key}
                inertia={item.inertia}
                href={item.href}
                active={item.active}
                icon={icons[item.icon]}
                badge={item.badge === 'unread_chat' ? unreadChatCount : (item.badge === 'pending_orders' ? pendingOrdersCount : null)}
                collapsed={collapsed}
            >
                {item.label}
            </SidebarLink>
        );
    }

    const Tag = item.inertia ? Link : 'a';
    // Solid only when the parent page itself is current — a child being
    // active is shown on the child's own row instead, so the parent
    // doesn't look "current" merely because it's expanded.
    const onOwnPage = item.active && !hasActiveChild;

    return (
        <div>
            <div
                className={`flex items-center rounded-lg text-sm font-medium transition-colors duration-150 ${
                    onOwnPage ? 'bg-[#8A3330] text-white shadow-sm shadow-[#8A3330]/20' : 'text-gray-600'
                } ${collapsed ? 'lg:justify-center lg:px-0' : ''}`}
            >
                <Tag
                    href={item.href}
                    data-nav-key={item.key}
                    title={item.label}
                    {...(item.inertia ? { 'data-turbo': 'false' } : {})}
                    className={`flex-1 flex items-center gap-3 px-3 py-2.5 min-w-0 ${onOwnPage ? '' : 'hover:text-[#8A3330]'}`}
                >
                    <span className={`shrink-0 h-5 w-5 [&>svg]:h-5 [&>svg]:w-5 ${onOwnPage ? 'text-white' : 'text-gray-400'}`}>
                        {icons[item.icon]}
                    </span>
                    <span className={`truncate flex-1 ${collapsed ? 'lg:hidden' : ''}`}>{item.label}</span>
                </Tag>
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        setOpen((current) => !current);
                    }}
                    aria-expanded={open}
                    aria-label={t('Toggle :item').replace(':item', item.label)}
                    className={`shrink-0 grid place-items-center h-8 w-8 mr-1.5 rounded-md ${
                        onOwnPage ? 'text-white/70 hover:text-white' : 'text-gray-400 hover:text-[#8A3330] hover:bg-[#F3E1DC]/70'
                    } ${collapsed ? 'lg:hidden' : ''}`}
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="2"
                        stroke="currentColor"
                        className={`h-3.5 w-3.5 transition-transform duration-150 ${open ? 'rotate-180' : ''}`}
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>

            {open && (
                <div className={`mt-0.5 space-y-0.5 ${collapsed ? 'lg:hidden' : ''}`}>
                    {item.children.map((child) => {
                        const ChildTag = child.inertia ? Link : 'a';

                        return (
                            <ChildTag
                                key={child.key}
                                href={child.href}
                                data-nav-key={child.key}
                                title={child.label}
                                {...(child.inertia ? { 'data-turbo': 'false' } : {})}
                                className={`block truncate rounded-lg py-2 pl-11 pr-3 text-sm transition-colors duration-150 ${
                                    child.active ? 'font-semibold text-[#8A3330]' : 'text-gray-500 hover:text-[#8A3330]'
                                }`}
                            >
                                {child.label}
                            </ChildTag>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const t = useTranslation();
    const {
        auth,
        logo,
        navigation,
        pendingOrdersCount: initialPendingOrdersCount,
        unreadChatCount: initialUnreadChatCount,
    } = usePage().props;
    const user = auth.user;

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(
        () => typeof window !== 'undefined' && localStorage.getItem('sidebarCollapsed') === '1',
    );
    const [pendingOrdersCount, setPendingOrdersCount] = useState(initialPendingOrdersCount ?? 0);
    const [unreadChatCount, setUnreadChatCount] = useState(initialUnreadChatCount ?? 0);
    const [staffAlerts, setStaffAlerts] = useState([]);

    useEffect(() => {
        localStorage.setItem('sidebarCollapsed', sidebarCollapsed ? '1' : '0');
    }, [sidebarCollapsed]);

    useEffect(() => {
        const pushStaffAlert = (message) => {
            const id = Date.now() + Math.random();
            setStaffAlerts((current) => [...current, { id, message }]);
            setTimeout(() => setStaffAlerts((current) => current.filter((a) => a.id !== id)), 8000);
        };

        window.Echo.private('kitchen').listen('.KitchenUpdated', (e) => setPendingOrdersCount(e.pending_orders_count));
        window.Echo.private('staff-alerts').listen('.StaffAssistanceRequested', (e) => pushStaffAlert(e.message));
        // This layout owns the chat-inbox channel's join/leave lifecycle —
        // Chat/Index.jsx also listens on it while mounted, but only ever
        // adds/removes its own callback (never leave()s the channel), so it
        // can't tear down this subscription out from under the sidebar badge.
        window.Echo.private(`chat-inbox.${user.id}`).listen('.ChatInboxUpdated', (e) => setUnreadChatCount(e.unread_count));

        // Keeps this user's own sessions.last_activity fresh while the tab
        // is open, even on pages that are otherwise all-websocket and make
        // no HTTP requests on their own — see the /heartbeat route comment.
        const heartbeat = setInterval(() => window.axios.post(route('heartbeat')), 60000);

        const leave = () => {
            clearInterval(heartbeat);
            window.Echo.leave('kitchen');
            window.Echo.leave('staff-alerts');
            window.Echo.leave(`chat-inbox.${user.id}`);
        };

        // Turbo Drive (see app.jsx) can navigate away from this page by
        // morphing the document instead of a hard reload, which never runs
        // React's own unmount/cleanup — turboCleanup is the only reliable
        // signal for that case. The returned function still covers a real
        // React unmount too, for correctness, though nothing unmounts this
        // layout today since there's no client-side router.
        turboCleanup(leave);

        return leave;
    }, []);

    const closeMobileSidebar = () => setSidebarOpen(false);

    return (
        <div className="min-h-screen bg-[#F7F0E3]">
            {/* Top bar */}
            <header className="bg-white border-b border-[#E5DDD0] sticky top-0 z-30">
                <div className="px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            type="button"
                            className="lg:hidden -ml-2 p-2 rounded-md text-gray-500 hover:bg-gray-100"
                            aria-label={t('Open menu')}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-6 w-6">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>

                        <Link href={route('superadmin.dashboard')} data-turbo="false" className="shrink-0">
                            <img
                                src={logo.url}
                                alt="88 Hot Spring Resort"
                                className={logo.wide ? 'h-9 w-auto object-contain' : 'h-10 w-10 rounded-full object-cover'}
                            />
                        </Link>
                        <span className="hidden sm:inline text-[#D9CCBA]">|</span>
                        <span className="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide bg-[#F3E1DC] text-[#8A3330]">
                            {t(roleLabelKeys[user.role] ?? user.role)}
                        </span>
                    </div>

                    <div className="flex items-center gap-2 sm:gap-4 shrink-0">
                        <Link
                            href={route('profile.edit')}
                            data-turbo="false"
                            className={`flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 ${
                                route().current('profile.edit') ? 'font-semibold text-[#8A3330]' : ''
                            }`}
                        >
                            <Avatar user={user} className="h-9 w-9 text-xs" />
                            <span className="hidden sm:inline">
                                {t('Signed in as')} <span className="font-semibold text-gray-800">{user.name}</span>
                            </span>
                        </Link>
                        <LanguageSwitcher />
                        {/* data-turbo="false": logout redirects to the Inertia Login page — see the Overview
                            sidebar link note for why Turbo can't be allowed to morph into it. Without this,
                            Turbo (loaded app-wide since app.jsx imports it too) intercepts this native form
                            POST and tries to XHR+morph the redirect response, leaving a blank page. */}
                        <form method="POST" action={route('logout')} data-turbo="false">
                            <input type="hidden" name="_token" value={usePage().props.csrf_token} />
                            <button type="submit" className="px-3 sm:px-4 py-1.5 rounded-lg border border-[#D9CCBA] text-sm font-medium text-gray-700 hover:bg-gray-50">
                                {t('Sign out')}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <Toast />

            {/* Live "Call a Staff" alerts — pushed in real time, separate from
                the session-flash Toast above since these can arrive at any moment. */}
            <div className="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 z-[70] flex flex-col gap-3 sm:w-96">
                {staffAlerts.map((alert) => (
                    <div key={alert.id} className="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 pl-4 pr-3 py-3.5 shadow-xl animate-fade-slide-up">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4 text-amber-700">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                        </div>
                        <p className="flex-1 pt-1 text-sm font-medium text-amber-900">{alert.message}</p>
                        <button
                            type="button"
                            onClick={() => setStaffAlerts((current) => current.filter((a) => a.id !== alert.id))}
                            className="shrink-0 rounded-md p-1 text-amber-400 hover:bg-amber-100 hover:text-amber-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>

            <div className="flex">
                {/* Mobile backdrop */}
                {sidebarOpen && (
                    <div onClick={closeMobileSidebar} className="fixed inset-0 bg-black/40 z-40 lg:hidden" />
                )}

                {/* Sidebar */}
                <aside
                    className={`fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-[#E5DDD0] px-4 py-5 overflow-y-auto overflow-x-hidden transform transition-all duration-200 ease-in-out lg:translate-x-0 lg:z-auto lg:shrink-0 lg:sticky lg:top-16 lg:h-[calc(100vh-4rem)] ${
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    } ${sidebarCollapsed ? 'lg:w-20' : 'lg:w-60'}`}
                >
                    {/* Mobile header */}
                    <div className="flex items-center justify-between mb-3 lg:hidden">
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-400 px-2">{t('Menu')}</p>
                        <button onClick={closeMobileSidebar} type="button" className="p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label={t('Close menu')}>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor" className="h-5 w-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Desktop header: label + collapse toggle */}
                    <div className={`hidden lg:flex items-center mb-4 px-2 ${sidebarCollapsed ? 'lg:justify-center lg:px-0' : 'justify-between'}`}>
                        <p className={`text-xs font-semibold uppercase tracking-wider text-gray-400 ${sidebarCollapsed ? 'lg:hidden' : ''}`}>{t('Menu')}</p>
                        <button
                            onClick={() => setSidebarCollapsed((v) => !v)}
                            type="button"
                            className="p-1.5 rounded-md text-gray-400 hover:bg-[#F3E1DC]/70 hover:text-[#8A3330] transition-colors duration-150"
                            aria-label={sidebarCollapsed ? t('Expand menu') : t('Collapse menu')}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                strokeWidth="1.8"
                                stroke="currentColor"
                                className={`h-4 w-4 transition-transform duration-200 ${sidebarCollapsed ? 'rotate-180' : ''}`}
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                            </svg>
                        </button>
                    </div>

                    <nav className="space-y-5" onClick={closeMobileSidebar}>
                        {navigation.length === 0 && (
                            <p className={`px-3 py-2 text-sm text-gray-400 ${sidebarCollapsed ? 'lg:hidden' : ''}`}>
                                {t('No areas assigned to your account yet.')}
                            </p>
                        )}

                        {navigation.map((group) => (
                            <SidebarGroup key={group.key} label={group.label} collapsed={sidebarCollapsed}>
                                {group.items.map((item) => (
                                    <NavItem
                                        key={item.key}
                                        item={item}
                                        collapsed={sidebarCollapsed}
                                        pendingOrdersCount={pendingOrdersCount}
                                        unreadChatCount={unreadChatCount}
                                        t={t}
                                    />
                                ))}
                            </SidebarGroup>
                        ))}
                    </nav>
                </aside>

                {/* Page content */}
                <main className="flex-1 min-w-0 p-4 sm:p-6">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
