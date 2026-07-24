import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function ToastItem({ id, type, message, onDismiss }) {
    const [width, setWidth] = useState('100%');

    useEffect(() => {
        const shrink = requestAnimationFrame(() => requestAnimationFrame(() => setWidth('0%')));
        const timer = setTimeout(() => onDismiss(id), 4000);
        return () => {
            cancelAnimationFrame(shrink);
            clearTimeout(timer);
        };
    }, [id, onDismiss]);

    const isError = type === 'error';

    return (
        <div className="relative overflow-hidden flex items-start gap-3 rounded-xl border border-[#E5DDD0] bg-white pl-4 pr-3 py-3.5 shadow-xl animate-fade-slide-up">
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${isError ? 'bg-red-100' : 'bg-green-100'}`}>
                {isError ? (
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="h-4 w-4 text-red-600">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                ) : (
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor" className="h-4 w-4 text-green-600">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                )}
            </div>

            <p className="flex-1 pt-1 text-sm font-medium text-gray-800">{message}</p>

            <button type="button" onClick={() => onDismiss(id)} className="shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div
                className={`absolute bottom-0 left-0 h-0.5 ${isError ? 'bg-red-400' : 'bg-green-400'}`}
                style={{ width, transitionProperty: 'width', transitionDuration: '4000ms', transitionTimingFunction: 'linear' }}
            />
        </div>
    );
}

export default function Toast() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const next = [];
        if (flash?.status) next.push({ id: 'status', type: 'success', message: flash.status });
        if (flash?.error) next.push({ id: 'error', type: 'error', message: flash.error });
        if (next.length) setToasts(next);
    }, [flash?.status, flash?.error]);

    const dismiss = (id) => setToasts((current) => current.filter((t) => t.id !== id));

    if (!toasts.length) return null;

    return (
        <div className="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 z-[60] flex flex-col gap-3 sm:w-96">
            {toasts.map((toast) => (
                <ToastItem key={toast.id} {...toast} onDismiss={dismiss} />
            ))}
        </div>
    );
}
