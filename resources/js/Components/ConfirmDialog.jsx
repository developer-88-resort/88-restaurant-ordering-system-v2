import Modal from '@/Components/Modal';
import { useTranslation } from '@/lib/i18n';

const WarningIcon = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.7" stroke="currentColor" {...props}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
);

/**
 * Generic Yes/No confirmation, replacing window.confirm() (which renders as
 * an ugly browser-chrome popup showing the raw host/IP) with something that
 * matches the app's own design. Wraps the existing (previously unused)
 * Components/Modal.jsx for the backdrop/transition/focus-trap plumbing.
 */
export default function ConfirmDialog({ show, title, message, confirmLabel, cancelLabel, destructive = true, onConfirm, onCancel }) {
    const t = useTranslation();

    return (
        <Modal show={show} onClose={onCancel} maxWidth="sm">
            <div className="p-6">
                <div className="flex items-start gap-4">
                    <div className={`grid h-11 w-11 shrink-0 place-items-center rounded-full ${destructive ? 'bg-red-100 text-red-600' : 'bg-[#F3E1DC] text-[#8A3330]'}`}>
                        <WarningIcon className="h-5 w-5" />
                    </div>
                    <div className="min-w-0 pt-1">
                        <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                        {message && <p className="mt-1 text-sm text-gray-500">{message}</p>}
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="rounded-lg border border-[#D9CCBA] px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
                    >
                        {cancelLabel ?? t('Cancel')}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        className={`rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition ${
                            destructive ? 'bg-red-600 hover:bg-red-700' : 'bg-[#8A3330] hover:bg-[#742927]'
                        }`}
                    >
                        {confirmLabel ?? t('Confirm')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
