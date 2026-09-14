import { useTranslation } from '@/lib/i18n';
import Swal from 'sweetalert2';

// Matches the server's images.* "max:5120" rule (kilobytes, 1024-based) —
// catching an oversized file here means the person finds out before
// filling out the rest of the form, not after submitting it.
const MAX_FILE_SIZE_BYTES = 5120 * 1024;

export default function ImageUploader({ existingImages, primaryId, onPrimaryChange, removedIds, onRemovedChange, newFiles, onNewFilesChange, error }) {
    const t = useTranslation();

    const toggleRemoved = (imageId) => {
        onRemovedChange(removedIds.includes(imageId) ? removedIds.filter((id) => id !== imageId) : [...removedIds, imageId]);
    };

    const handleFiles = (fileList) => {
        const files = Array.from(fileList);
        const oversized = files.filter((file) => file.size > MAX_FILE_SIZE_BYTES);
        const validFiles = files.filter((file) => file.size <= MAX_FILE_SIZE_BYTES).slice(0, 6);

        if (oversized.length > 0) {
            Swal.fire({
                icon: 'error',
                title: t('File too large'),
                text:
                    oversized.length === 1
                        ? t(':name is larger than 5MB. Please choose a smaller image.').replace(':name', oversized[0].name)
                        : t(':count images are larger than 5MB and were skipped.').replace(':count', oversized.length),
                confirmButtonColor: '#8A3330',
            });
        }

        if (validFiles.length > 0) {
            onNewFilesChange(validFiles.map((file) => ({ file, url: URL.createObjectURL(file) })));
        }
    };

    return (
        <div>
            {existingImages.length > 0 && (
                <>
                    <p className="text-xs font-medium text-gray-500 mb-2">{t('Current images')}</p>
                    <div className="flex flex-wrap gap-4 mb-5">
                        {existingImages.map((image) => {
                            const removed = removedIds.includes(image.id);
                            return (
                                <div key={image.id} className="w-24">
                                    <img
                                        src={image.url}
                                        alt=""
                                        className={`h-24 w-24 rounded-lg object-cover border-2 transition ${
                                            removed ? 'opacity-30 grayscale border-[#E5DDD0]' : primaryId === image.id ? 'border-[#8A3330]' : 'border-[#E5DDD0]'
                                        }`}
                                    />
                                    <div className="mt-1.5 flex flex-col gap-1 text-[11px]">
                                        {!removed && (
                                            <label className="flex items-center gap-1.5 cursor-pointer text-gray-600">
                                                <input
                                                    type="radio"
                                                    checked={primaryId === image.id}
                                                    onChange={() => onPrimaryChange(image.id)}
                                                    className="h-3 w-3 text-[#8A3330] focus:ring-[#8A3330]"
                                                />
                                                {t('Primary')}
                                            </label>
                                        )}
                                        <label className="flex items-center gap-1.5 cursor-pointer text-gray-500 hover:text-red-600">
                                            <input
                                                type="checkbox"
                                                checked={removed}
                                                onChange={() => toggleRemoved(image.id)}
                                                className="h-3 w-3 rounded text-red-600 focus:ring-red-500"
                                            />
                                            {t('Remove')}
                                        </label>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </>
            )}

            <label className="flex flex-col items-center justify-center gap-1 border-2 border-dashed border-[#D9CCBA] rounded-xl px-4 py-6 cursor-pointer hover:border-[#8A3330] hover:bg-[#FAF6EE] transition text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="#8A3330" className="h-6 w-6">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 16.5V9.75m0 0l-3.75 3.75M12 9.75l3.75 3.75M3 17.25V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18v-.75" />
                </svg>
                <span className="text-sm font-medium text-[#8A3330]">{t('Add images')}</span>
                <span className="text-xs text-gray-400">{t('Up to 6 photos, 5MB each')}</span>
                <input type="file" multiple accept="image/*" className="sr-only" onChange={(e) => handleFiles(e.target.files)} />
            </label>

            {newFiles.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-3">
                    {newFiles.map((file, index) => (
                        <img key={index} src={file.url} alt="" className="h-24 w-24 rounded-lg object-cover border border-[#E5DDD0]" />
                    ))}
                </div>
            )}

            {error && <p className="text-sm text-red-600 mt-2">{error}</p>}
        </div>
    );
}
