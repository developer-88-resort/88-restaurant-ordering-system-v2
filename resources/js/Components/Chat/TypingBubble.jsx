import Avatar from '@/Components/Avatar';
import { useTranslation } from '@/lib/i18n';

export default function TypingBubble({ user }) {
    const t = useTranslation();

    return (
        <div className="flex items-end gap-2">
            <Avatar user={user} className="h-7 w-7 text-[10px] shrink-0" />
            <div className="flex items-center gap-1.5 rounded-2xl rounded-bl-sm border border-[#E5DDD0] bg-white px-3.5 py-2.5">
                <span className="text-xs text-[#8A7B6D]">{t('Typing')}</span>
                <span className="flex items-center gap-1">
                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8A7B6D]" style={{ animationDelay: '0ms' }} />
                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8A7B6D]" style={{ animationDelay: '150ms' }} />
                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#8A7B6D]" style={{ animationDelay: '300ms' }} />
                </span>
            </div>
        </div>
    );
}
