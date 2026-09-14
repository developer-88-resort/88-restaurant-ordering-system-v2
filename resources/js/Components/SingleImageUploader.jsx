import { useTranslation } from '@/lib/i18n';

/**
 * One banner/promotional image — a lighter-weight sibling to ImageUploader
 * (which is built for up to 6 images with primary-select + per-image
 * remove, a mismatched shape for a single field).
 */
export default function SingleImageUploader({ existingImageUrl, newFile, onFileChange, removed, onRemovedChange, error, wide = false }) {
    const t = useTranslation();

    const handleFile = (fileList) => {
        const file = fileList?.[0] ?? null;
        onFileChange(file ? { file, url: URL.createObjectURL(file) } : null);
        if (file) onRemovedChange(false);
    };

    const showExisting = existingImageUrl && !removed && !newFile;
    const showPreview = newFile && !removed;

    return (
        <div>
            {(showExisting || showPreview) && (
                <div className={`mb-3 ${wide ? 'w-60' : 'w-40'}`}>
                    <img
                        src={showPreview ? newFile.url : existingImageUrl}
                        alt=""
                        className={wide
                            ? 'aspect-[3/1] w-60 rounded-lg border border-[#E5DDD0] object-cover'
                            : 'h-28 w-40 rounded-lg border border-[#E5DDD0] object-cover'}
                    />
                    <button
                        type="button"
                        onClick={() => {
                            if (newFile) {
                                onFileChange(null);
                            } else {
                                onRemovedChange(true);
                            }
                        }}
                        className="mt-1.5 text-xs font-semibold text-red-600 hover:underline"
                    >
                        {t('Remove image')}
                    </button>
                </div>
            )}

            {!showExisting && !showPreview && (
                <label className={`flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-[#E5DDD0] bg-[#FCFAF7] text-center text-xs font-medium text-[#9A8B84] transition hover:border-[#8A3330]/40 hover:text-[#8A3330] ${wide ? 'aspect-[3/1] w-60' : 'h-28 w-40'}`}>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="h-6 w-6">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 16.5V9.75m0 0l-3.75 3.75M12 9.75l3.75 3.75M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                    </svg>
                    {t('Upload image')}
                    <input type="file" accept="image/*" className="hidden" onChange={(e) => handleFile(e.target.files)} />
                </label>
            )}

            {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
        </div>
    );
}
