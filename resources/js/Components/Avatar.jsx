export default function Avatar({ user, className = '' }) {
    if (user?.avatar_url) {
        return (
            <img
                src={user.avatar_url}
                alt={user.name}
                className={`rounded-full object-cover border border-[#E5DDD0] ${className}`}
            />
        );
    }

    return (
        <div className={`rounded-full bg-[#F3E1DC] text-[#8A3330] flex items-center justify-center font-semibold shrink-0 ${className}`}>
            {user?.initials ?? '?'}
        </div>
    );
}
