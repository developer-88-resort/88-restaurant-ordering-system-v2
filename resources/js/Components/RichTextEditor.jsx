import { useTranslation } from '@/lib/i18n';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import { FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style';
import { Fragment } from '@tiptap/pm/model';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useRef, useState } from 'react';

// Tiptap's stock toggleBulletList()/toggleOrderedList() wraps the ENTIRE
// selected block range in a single list item (a well-known
// prosemirror-schema-list behaviour — wrapInList operates on one
// $from.blockRange($to), not one range per block), so selecting a
// multi-line set-meal description and clicking "Bulleted list" produces
// one bullet holding every line, not one bullet per line. This walks each
// top-level block in the selection and gives it its own list item —
// additionally splitting a paragraph's internal hard breaks (from
// Shift+Enter, or from pasting a plain-text list — see
// transformPastedText below) into separate items too, since those are
// invisible line joins within a single paragraph, not separate blocks.
function toggleListOneItemPerLine(editor, listTypeName) {
    const { state, view } = editor;
    const { schema, selection } = state;
    const { $from, $to } = selection;
    const range = $from.blockRange($to);
    if (!range) return false;

    const listType = schema.nodes[listTypeName];
    const itemType = schema.nodes.listItem;
    const paragraphType = schema.nodes.paragraph;
    const hardBreakType = schema.nodes.hardBreak;

    const items = [];
    range.parent.forEach((child, _offset, index) => {
        if (index < range.startIndex || index >= range.endIndex) return;

        if (child.type === paragraphType && hardBreakType) {
            let run = [];
            const flush = () => {
                items.push(itemType.create(null, paragraphType.create(null, Fragment.fromArray(run))));
                run = [];
            };
            child.forEach((inline) => {
                if (inline.type === hardBreakType) flush();
                else run.push(inline);
            });
            flush();
            return;
        }

        items.push(itemType.create(null, child.type === paragraphType ? child : paragraphType.create(null, child.content)));
    });
    if (items.length === 0) return false;

    // Drop lines that ended up blank (a trailing newline from a pasted
    // list, say) — but never all the way down to nothing.
    const meaningful = items.filter((item) => item.textContent.trim() !== '');
    const finalItems = meaningful.length > 0 ? meaningful : items;

    const tr = state.tr.replaceWith(range.start, range.end, listType.create(null, finalItems));
    view.dispatch(tr);
    editor.commands.focus();
    return true;
}

// The other direction: unwrap a list back into plain paragraphs, one per
// item. Necessary because StarterKit's TrailingNode extension (on by
// default) auto-appends an empty paragraph after a list — a good thing
// normally (lets you click below a list to keep typing), but it means the
// document is never JUST a list at the top level. Tiptap's stock
// toggleBulletList()/toggleOrderedList() has a fast path specifically for
// "the whole document (Ctrl+A) is a single list node" that silently
// produces malformed nested <li> markup once that trailing paragraph
// sibling exists — this sidesteps the stock command entirely rather than
// depend on that special case.
function untoggleListToParagraphs(editor, listTypeName) {
    const { state, view } = editor;
    const { doc, schema, selection } = state;
    const { from, to } = selection;
    const listType = schema.nodes[listTypeName];
    const paragraphType = schema.nodes.paragraph;

    const targets = [];
    doc.forEach((node, offset) => {
        const nodeFrom = offset;
        const nodeTo = offset + node.nodeSize;
        if (node.type === listType && nodeFrom < to && nodeTo > from) targets.push({ node, from: nodeFrom, to: nodeTo });
    });
    if (targets.length === 0) return false;

    let tr = state.tr;
    for (let i = targets.length - 1; i >= 0; i--) {
        const { node, from: nodeFrom, to: nodeTo } = targets[i];
        const paragraphs = [];
        node.forEach((listItem) => {
            listItem.forEach((child) => {
                paragraphs.push(child.type === paragraphType ? child : paragraphType.create(null, child.content));
            });
        });
        tr = tr.replaceWith(nodeFrom, nodeTo, Fragment.fromArray(paragraphs));
    }
    view.dispatch(tr);
    editor.commands.focus();
    return true;
}

// editor.isActive('bulletList') requires the matched list node to cover
// the WHOLE selection — which fails for a Ctrl+A selection, since that
// spans the list PLUS the empty trailing paragraph StarterKit's
// TrailingNode extension appends after it. isActive then wrongly reports
// "not a list" for a selection that plainly contains one, so the toolbar
// buttons below would take the wrap-as-new-list path on a second click
// instead of unwrapping, re-wrapping the existing <ul> and corrupting it.
// This checks for "a list of this type intersects the selection" instead
// of "covers all of it" — used both to decide which direction a click
// should go and to draw the button's pressed state.
function selectionTouchesList(editor, listTypeName) {
    const { doc, schema, selection } = editor.state;
    const listType = schema.nodes[listTypeName];
    const { from, to } = selection;
    let found = false;
    doc.forEach((node, offset) => {
        if (found) return;
        const nodeFrom = offset;
        const nodeTo = offset + node.nodeSize;
        if (node.type === listType && nodeFrom < to && nodeTo > from) found = true;
    });
    return found;
}

const COLOR_SWATCHES = [
    { label: 'Maroon', value: '#8A3330' },
    { label: 'Black', value: '#1F2937' },
    { label: 'Gray', value: '#6B7280' },
    { label: 'Green', value: '#15803D' },
    { label: 'Blue', value: '#1D4ED8' },
    { label: 'Purple', value: '#8A7B9E' },
    { label: 'Orange', value: '#C2410C' },
    { label: 'Red', value: '#DC2626' },
];

// Pastel marker-pen colors — the highlight mark keeps the text's own color
// ("color: inherit", see Highlight extension), so these stay light enough
// for dark text to stay readable on top of them.
const HIGHLIGHT_SWATCHES = [
    { label: 'Yellow', value: '#FEF08A' },
    { label: 'Green', value: '#BBF7D0' },
    { label: 'Blue', value: '#BFDBFE' },
    { label: 'Pink', value: '#FBCFE8' },
    { label: 'Orange', value: '#FED7AA' },
    { label: 'Purple', value: '#E9D5FF' },
];

// Every family here is loaded app-wide via Bunny Fonts (see
// resources/views/app.blade.php) except the two system ones (Georgia,
// Courier New, which every OS ships) — never an arbitrary name a
// customer's browser would have to guess at or silently fall back from.
// Grouped the way a font menu is always grouped: by the visual family it
// reads as, not alphabetically.
const FONT_FAMILY_GROUPS = [
    {
        group: 'Sans-serif',
        fonts: [
            { label: 'Figtree', value: 'Figtree, sans-serif' },
            { label: 'Inter', value: 'Inter, sans-serif' },
            { label: 'Poppins', value: 'Poppins, sans-serif' },
            { label: 'Montserrat', value: 'Montserrat, sans-serif' },
            { label: 'Nunito', value: 'Nunito, sans-serif' },
            { label: 'Lexend', value: 'Lexend, sans-serif' },
        ],
    },
    {
        group: 'Serif',
        fonts: [
            { label: 'Playfair Display', value: '"Playfair Display", serif' },
            { label: 'Merriweather', value: 'Merriweather, serif' },
            { label: 'Lora', value: 'Lora, serif' },
            { label: 'EB Garamond', value: '"EB Garamond", serif' },
            { label: 'Georgia', value: 'Georgia, serif' },
        ],
    },
    {
        group: 'Display & script',
        fonts: [
            { label: 'Oswald', value: 'Oswald, sans-serif' },
            { label: 'Comfortaa', value: 'Comfortaa, sans-serif' },
            { label: 'Pacifico', value: 'Pacifico, cursive' },
            { label: 'Caveat', value: 'Caveat, cursive' },
        ],
    },
    {
        group: 'Monospace',
        fonts: [
            { label: 'Roboto Mono', value: '"Roboto Mono", monospace' },
            { label: 'Courier New', value: '"Courier New", monospace' },
        ],
    },
];

const FONT_SIZES = [
    { label: 'Small', value: '12px' },
    { label: 'Normal', value: '' },
    { label: 'Large', value: '18px' },
    { label: 'Extra large', value: '24px' },
    { label: 'Huge', value: '32px' },
];

function UndoIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L4 10m0 0l5-5m-5 5h11a4 4 0 010 8h-1" />
        </svg>
    );
}

function RedoIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 15l5-5m0 0l-5-5m5 5H9a4 4 0 000 8h1" />
        </svg>
    );
}

function AlignLeftIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" d="M3.75 6h16.5M3.75 12h10.5M3.75 18h13.5" />
        </svg>
    );
}

function AlignCenterIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" d="M3.75 6h16.5M6.75 12h10.5M5.25 18h13.5" />
        </svg>
    );
}

function AlignRightIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" d="M3.75 6h16.5M9.75 12h10.5M6.75 18h13.5" />
        </svg>
    );
}

function LinkIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className="h-4 w-4">
            <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
        </svg>
    );
}

// A small toolbar toggle button — the same "tan track, maroon active pill"
// idiom as ItemForm.jsx's Pricing Type segmented control, just sized down
// to icon-button proportions and used individually rather than as a group.
function ToolbarButton({ active, disabled, onClick, title, children }) {
    return (
        <button
            type="button"
            title={title}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className={`flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-30 ${
                active ? 'bg-[#8A3330] text-white' : 'text-gray-600 hover:bg-[#F3E1DC]/70'
            }`}
        >
            {children}
        </button>
    );
}

function ToolbarDivider() {
    return <span className="mx-0.5 h-5 w-px shrink-0 bg-[#D9CCBA]" />;
}

// Reuses EmojiPicker.jsx's exact floating-panel chrome and click-outside
// pattern (rounded-2xl border shadow, mousedown listener) for both the
// color swatch grid and the link-URL prompt below.
function useClickOutside(onClose) {
    const ref = useRef(null);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [onClose]);

    return ref;
}

function ColorPopover({ editor, onClose }) {
    const t = useTranslation();
    const ref = useClickOutside(onClose);

    return (
        <div
            ref={ref}
            className="absolute left-0 top-full z-10 mt-1 w-48 rounded-2xl border border-[#E5DDD0] bg-white p-2 shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)]"
        >
            <div className="grid grid-cols-4 gap-1.5">
                {COLOR_SWATCHES.map((swatch) => (
                    <button
                        key={swatch.value}
                        type="button"
                        title={swatch.label}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                            editor.chain().focus().setColor(swatch.value).run();
                            onClose();
                        }}
                        className="h-7 w-7 rounded-full border border-black/10"
                        style={{ backgroundColor: swatch.value }}
                    />
                ))}
            </div>
            <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                    editor.chain().focus().unsetColor().run();
                    onClose();
                }}
                className="mt-2 w-full rounded-md border border-[#D9CCBA] py-1 text-[11px] font-medium text-gray-600 hover:bg-[#FAF6EE]"
            >
                {t('Remove color')}
            </button>
        </div>
    );
}

function HighlightPopover({ editor, onClose }) {
    const t = useTranslation();
    const ref = useClickOutside(onClose);

    return (
        <div
            ref={ref}
            className="absolute left-0 top-full z-10 mt-1 w-44 rounded-2xl border border-[#E5DDD0] bg-white p-2 shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)]"
        >
            <div className="grid grid-cols-3 gap-1.5">
                {HIGHLIGHT_SWATCHES.map((swatch) => (
                    <button
                        key={swatch.value}
                        type="button"
                        title={swatch.label}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                            editor.chain().focus().setHighlight({ color: swatch.value }).run();
                            onClose();
                        }}
                        className="h-7 w-7 rounded-full border border-black/10"
                        style={{ backgroundColor: swatch.value }}
                    />
                ))}
            </div>
            <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                    editor.chain().focus().unsetHighlight().run();
                    onClose();
                }}
                className="mt-2 w-full rounded-md border border-[#D9CCBA] py-1 text-[11px] font-medium text-gray-600 hover:bg-[#FAF6EE]"
            >
                {t('Remove highlight')}
            </button>
        </div>
    );
}

function LinkPopover({ editor, onClose }) {
    const t = useTranslation();
    const ref = useClickOutside(onClose);
    const [url, setUrl] = useState(editor.getAttributes('link').href || '');

    const apply = () => {
        const trimmed = url.trim();
        if (trimmed === '') {
            editor.chain().focus().unsetLink().run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink({ href: trimmed }).run();
        }
        onClose();
    };

    return (
        <div
            ref={ref}
            className="absolute left-0 top-full z-10 mt-1 w-64 rounded-2xl border border-[#E5DDD0] bg-white p-2 shadow-[0_14px_38px_-18px_rgba(55,35,30,0.45)]"
        >
            <input
                type="text"
                autoFocus
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        apply();
                    }
                }}
                placeholder="https://…"
                className="w-full rounded-lg border border-[#D9CCBA] px-3 py-1.5 text-sm focus:border-[#8A3330] focus:outline-none focus:ring-1 focus:ring-[#8A3330]"
            />
            <div className="mt-2 flex gap-1.5">
                <button
                    type="button"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={apply}
                    className="flex-1 rounded-md bg-[#8A3330] py-1 text-[11px] font-semibold text-white hover:bg-[#742927]"
                >
                    {editor.isActive('link') ? t('Update') : t('Add')}
                </button>
                {editor.isActive('link') && (
                    <button
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                            editor.chain().focus().unsetLink().run();
                            onClose();
                        }}
                        className="flex-1 rounded-md border border-[#D9CCBA] py-1 text-[11px] font-medium text-gray-600 hover:bg-[#FAF6EE]"
                    >
                        {t('Remove link')}
                    </button>
                )}
            </div>
        </div>
    );
}

function RichTextToolbar({ editor }) {
    const t = useTranslation();
    const [openPanel, setOpenPanel] = useState(null); // null | 'color' | 'highlight' | 'link'

    if (!editor) return null;

    const headingValue = editor.isActive('heading', { level: 1 })
        ? '1'
        : editor.isActive('heading', { level: 2 })
          ? '2'
          : editor.isActive('heading', { level: 3 })
            ? '3'
            : 'p';

    const fontFamilyValue = editor.getAttributes('textStyle').fontFamily || '';
    const fontSizeValue = editor.getAttributes('textStyle').fontSize || '';

    return (
        <div className="flex flex-wrap items-center gap-1 rounded-t-md border-b border-[#D9CCBA] bg-[#FAF6EE] px-2 py-1.5">
            <ToolbarButton title={t('Undo')} disabled={!editor.can().undo()} onClick={() => editor.chain().focus().undo().run()}>
                <UndoIcon />
            </ToolbarButton>
            <ToolbarButton title={t('Redo')} disabled={!editor.can().redo()} onClick={() => editor.chain().focus().redo().run()}>
                <RedoIcon />
            </ToolbarButton>

            <ToolbarDivider />

            <select
                value={headingValue}
                onChange={(e) => {
                    const val = e.target.value;
                    if (val === 'p') {
                        editor.chain().focus().setParagraph().run();
                    } else {
                        editor.chain().focus().toggleHeading({ level: Number(val) }).run();
                    }
                }}
                className="h-7 rounded-md border-gray-300 py-0 text-xs focus:border-[#8A3330] focus:ring-[#8A3330]"
            >
                <option value="p">{t('Normal text')}</option>
                <option value="1">{t('Heading 1')}</option>
                <option value="2">{t('Heading 2')}</option>
                <option value="3">{t('Heading 3')}</option>
            </select>

            <select
                value={fontFamilyValue}
                onChange={(e) => {
                    const val = e.target.value;
                    if (val === '') editor.chain().focus().unsetFontFamily().run();
                    else editor.chain().focus().setFontFamily(val).run();
                }}
                className="h-7 rounded-md border-gray-300 py-0 text-xs focus:border-[#8A3330] focus:ring-[#8A3330]"
            >
                <option value="">{t('Font')}</option>
                {FONT_FAMILY_GROUPS.map((group) => (
                    <optgroup key={group.group} label={t(group.group)}>
                        {group.fonts.map((font) => (
                            <option key={font.value} value={font.value} style={{ fontFamily: font.value }}>
                                {font.label}
                            </option>
                        ))}
                    </optgroup>
                ))}
            </select>

            <select
                value={fontSizeValue}
                onChange={(e) => {
                    const val = e.target.value;
                    if (val === '') editor.chain().focus().unsetFontSize().run();
                    else editor.chain().focus().setFontSize(val).run();
                }}
                className="h-7 rounded-md border-gray-300 py-0 text-xs focus:border-[#8A3330] focus:ring-[#8A3330]"
            >
                {FONT_SIZES.map((size) => (
                    <option key={size.value} value={size.value}>
                        {size.label}
                    </option>
                ))}
            </select>

            <ToolbarDivider />

            <ToolbarButton title={t('Bold')} active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}>
                <span className="font-bold">B</span>
            </ToolbarButton>
            <ToolbarButton title={t('Italic')} active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}>
                <span className="italic">I</span>
            </ToolbarButton>
            <ToolbarButton title={t('Underline')} active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()}>
                <span className="underline">U</span>
            </ToolbarButton>
            <ToolbarButton title={t('Strikethrough')} active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()}>
                <span className="line-through">S</span>
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton
                title={t('Bulleted list')}
                active={selectionTouchesList(editor, 'bulletList')}
                onClick={() => {
                    if (selectionTouchesList(editor, 'bulletList')) {
                        if (!untoggleListToParagraphs(editor, 'bulletList')) editor.chain().focus().toggleBulletList().run();
                    } else if (!toggleListOneItemPerLine(editor, 'bulletList')) editor.chain().focus().toggleBulletList().run();
                }}
            >
                •
            </ToolbarButton>
            <ToolbarButton
                title={t('Numbered list')}
                active={selectionTouchesList(editor, 'orderedList')}
                onClick={() => {
                    if (selectionTouchesList(editor, 'orderedList')) {
                        if (!untoggleListToParagraphs(editor, 'orderedList')) editor.chain().focus().toggleOrderedList().run();
                    } else if (!toggleListOneItemPerLine(editor, 'orderedList')) editor.chain().focus().toggleOrderedList().run();
                }}
            >
                1.
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton title={t('Align left')} active={editor.isActive({ textAlign: 'left' })} onClick={() => editor.chain().focus().setTextAlign('left').run()}>
                <AlignLeftIcon />
            </ToolbarButton>
            <ToolbarButton title={t('Align center')} active={editor.isActive({ textAlign: 'center' })} onClick={() => editor.chain().focus().setTextAlign('center').run()}>
                <AlignCenterIcon />
            </ToolbarButton>
            <ToolbarButton title={t('Align right')} active={editor.isActive({ textAlign: 'right' })} onClick={() => editor.chain().focus().setTextAlign('right').run()}>
                <AlignRightIcon />
            </ToolbarButton>

            <ToolbarDivider />

            <div className="relative">
                <ToolbarButton title={t('Text color')} onClick={() => setOpenPanel(openPanel === 'color' ? null : 'color')}>
                    <span className="flex h-4 w-4 items-center justify-center rounded-full border border-black/10 bg-gradient-to-br from-[#8A3330] via-[#1D4ED8] to-[#15803D]" />
                </ToolbarButton>
                {openPanel === 'color' && <ColorPopover editor={editor} onClose={() => setOpenPanel(null)} />}
            </div>

            <div className="relative">
                <ToolbarButton
                    title={t('Highlight')}
                    active={editor.isActive('highlight')}
                    onClick={() => setOpenPanel(openPanel === 'highlight' ? null : 'highlight')}
                >
                    <span className="flex h-4 w-4 items-center justify-center rounded border border-black/10 bg-gradient-to-br from-[#FEF08A] via-[#FBCFE8] to-[#BFDBFE]" />
                </ToolbarButton>
                {openPanel === 'highlight' && <HighlightPopover editor={editor} onClose={() => setOpenPanel(null)} />}
            </div>

            <div className="relative">
                <ToolbarButton title={t('Link')} active={editor.isActive('link')} onClick={() => setOpenPanel(openPanel === 'link' ? null : 'link')}>
                    <LinkIcon />
                </ToolbarButton>
                {openPanel === 'link' && <LinkPopover editor={editor} onClose={() => setOpenPanel(null)} />}
            </div>
        </div>
    );
}

/**
 * A controlled rich-text editor for menu item descriptions — Tiptap under
 * the hood, but a fully custom toolbar built from this app's own existing
 * brand idioms rather than a generic editor UI kit. Value in/out is always
 * an HTML string; the caller is responsible for treating it as such
 * (sanitized server-side before it's ever displayed to a customer — see
 * app/Support/MenuDescriptionPurifier.php).
 */
export default function RichTextEditor({ id, value, onChange, placeholder, className = '' }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                blockquote: false,
                codeBlock: false,
                horizontalRule: false,
                heading: { levels: [1, 2, 3] },
                link: {
                    openOnClick: false,
                    autolink: true,
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' },
                },
            }),
            TextStyle,
            Color,
            FontFamily,
            FontSize,
            Highlight.configure({ multicolor: true }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder: placeholder || '' }),
        ],
        content: value || '',
        editorProps: {
            attributes: {
                ...(id ? { id } : {}),
                class: 'rich-text min-h-[120px] px-3 py-2 text-sm focus:outline-none',
            },
            // Pasting plain text (e.g. a set-meal's item list copied from
            // Notes/SMS) otherwise lands as ONE paragraph with the lines
            // joined by <br>s — ProseMirror's default clipboard handling
            // only splits into separate paragraphs on a BLANK line. Since
            // this field's whole point is one line per dish, every line
            // break here is meant as its own paragraph.
            transformPastedText: (text) =>
                text
                    .split('\n')
                    .map((line) => line.trim())
                    .filter(Boolean)
                    .join('\n\n'),
        },
        // Tiptap v3 opts out of re-rendering on every transaction by
        // default — fine for content changes (those already cascade a
        // re-render via onUpdate -> onChange -> the parent form's state),
        // but a mark applied to a COLLAPSED cursor (no text selected, e.g.
        // clicking the font-size stepper before typing) only touches
        // storedMarks, never the document, so onUpdate never fires. Without
        // this flag the toolbar's own isActive()/getAttributes() reads
        // (bold state, font size, heading level, ...) go stale until some
        // unrelated edit happens to force a re-render.
        shouldRerenderOnTransaction: true,
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // Only re-push external value changes that didn't originate from this
    // editor's own onUpdate (e.g. the Edit page's item.description arriving
    // after mount) — otherwise every keystroke's own round-trip through
    // parent state would fight the cursor position.
    useEffect(() => {
        if (editor && (value || '') !== editor.getHTML()) {
            editor.commands.setContent(value || '', false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, editor]);

    if (!editor) return null;

    return (
        <div className={`overflow-hidden rounded-md border border-[#D9CCBA] focus-within:border-[#8A3330] focus-within:ring-1 focus-within:ring-[#8A3330] ${className}`}>
            <RichTextToolbar editor={editor} />
            <EditorContent editor={editor} />
        </div>
    );
}
