import { Component } from 'react';

/**
 * Last line of defense against a render crash. Without this, a single
 * uncaught error anywhere in the tree (e.g. code that assumes a field
 * exists when the server didn't send it) unmounts the ENTIRE page to a
 * blank white screen — no message, no way back, and whatever the user was
 * mid-way through typing is gone. This shows a recoverable message instead.
 */
export default class ErrorBoundary extends Component {
    state = { hasError: false };

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error, info) {
        // eslint-disable-next-line no-console
        console.error('Unhandled render error:', error, info?.componentStack);
    }

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <div className="flex min-h-screen items-center justify-center bg-[#FAF6EE] px-4">
                <div className="max-w-sm rounded-xl border border-[#E5DDD0] bg-white p-6 text-center shadow-sm">
                    <p className="text-base font-semibold text-gray-900">Something went wrong</p>
                    <p className="mt-2 text-sm text-gray-500">
                        This screen hit an unexpected error. Anything already saved on the
                        server is safe — reload to try again.
                    </p>
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="mt-4 inline-flex items-center rounded-lg bg-[#8A3330] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#742927]"
                    >
                        Reload page
                    </button>
                </div>
            </div>
        );
    }
}
