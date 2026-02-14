'use client';

import '@/../css/editor.css';
import { cn } from '@/lib/utils';
import DragHandle from '@tiptap/extension-drag-handle-react';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { CharacterCount, Placeholder } from '@tiptap/extensions';
import type { Editor } from '@tiptap/react';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { GripVertical } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from './ui/button';

import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Italic,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Undo2,
} from 'lucide-react';

interface RichTextEditorProps {
    value: string;
    onCommit: (value: string) => void;
    disabled?: boolean;
    size?: 'compact' | 'default';
    className?: string;
    placeholder?: string;
    characterLimit?: number;
    hideBorder?: boolean;
    disableDragHandle?: boolean;
}

function MenuBar({ editor }: { editor: Editor }) {
    const editorState = useEditorState({
        editor,
        selector: (ctx) => {
            return {
                isBold: ctx.editor.isActive('bold') ?? false,
                canBold: ctx.editor.can().chain().toggleBold().run() ?? false,
                isItalic: ctx.editor.isActive('italic') ?? false,
                canItalic:
                    ctx.editor.can().chain().toggleItalic().run() ?? false,
                isStrike: ctx.editor.isActive('strike') ?? false,
                canStrike:
                    ctx.editor.can().chain().toggleStrike().run() ?? false,
                isCode: ctx.editor.isActive('code') ?? false,
                canCode: ctx.editor.can().chain().toggleCode().run() ?? false,
                canClearMarks:
                    ctx.editor.can().chain().unsetAllMarks().run() ?? false,
                isParagraph: ctx.editor.isActive('paragraph') ?? false,
                isHeading1:
                    ctx.editor.isActive('heading', { level: 1 }) ?? false,
                isHeading2:
                    ctx.editor.isActive('heading', { level: 2 }) ?? false,
                isHeading3:
                    ctx.editor.isActive('heading', { level: 3 }) ?? false,
                isHeading4:
                    ctx.editor.isActive('heading', { level: 4 }) ?? false,
                isHeading5:
                    ctx.editor.isActive('heading', { level: 5 }) ?? false,
                isHeading6:
                    ctx.editor.isActive('heading', { level: 6 }) ?? false,
                isBulletList: ctx.editor.isActive('bulletList') ?? false,
                isOrderedList: ctx.editor.isActive('orderedList') ?? false,
                isCodeBlock: ctx.editor.isActive('codeBlock') ?? false,
                isBlockquote: ctx.editor.isActive('blockquote') ?? false,
                isAlignLeft:
                    ctx.editor.isActive({ textAlign: 'left' }) ?? false,
                isAlignCenter:
                    ctx.editor.isActive({ textAlign: 'center' }) ?? false,
                isAlignRight:
                    ctx.editor.isActive({ textAlign: 'right' }) ?? false,
                isAlignJustify:
                    ctx.editor.isActive({ textAlign: 'justify' }) ?? false,
                canUndo: ctx.editor.can().chain().undo().run() ?? false,
                canRedo: ctx.editor.can().chain().redo().run() ?? false,
            };
        },
    });

    const handleMouseDown = (e: React.MouseEvent) => {
        e.preventDefault();
    };

    return (
        <div className="border-b bg-muted/30 p-2" onMouseDown={handleMouseDown}>
            <div className="flex flex-wrap items-center gap-1">
                {/* Text Formatting Group */}
                <div className="flex items-center gap-1 border-r pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                        disabled={!editorState.canBold}
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isBold && 'bg-accent',
                        )}
                        title="Bold"
                    >
                        <Bold className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                        disabled={!editorState.canItalic}
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isItalic && 'bg-accent',
                        )}
                        title="Italic"
                    >
                        <Italic className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleStrike().run()
                        }
                        disabled={!editorState.canStrike}
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isStrike && 'bg-accent',
                        )}
                        title="Strikethrough"
                    >
                        <Strikethrough className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleCode().run()
                        }
                        disabled={!editorState.canCode}
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isCode && 'bg-accent',
                        )}
                        title="Code"
                    >
                        <Code className="h-4 w-4" />
                    </Button>
                </div>

                {/* Heading Buttons */}
                <div className="flex items-center gap-1 border-r pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 1 })
                                .run()
                        }
                        className={cn(
                            'h-8 px-2',
                            editorState.isHeading1 && 'bg-accent',
                        )}
                        title="Heading 1"
                    >
                        <span className="text-sm font-bold">H1</span>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                        className={cn(
                            'h-8 px-2',
                            editorState.isHeading2 && 'bg-accent',
                        )}
                        title="Heading 2"
                    >
                        <span className="text-sm font-bold">H2</span>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                        className={cn(
                            'h-8 px-2',
                            editorState.isHeading3 && 'bg-accent',
                        )}
                        title="Heading 3"
                    >
                        <span className="text-sm font-bold">H3</span>
                    </Button>
                </div>

                {/* Lists & Blocks Group */}
                <div className="flex items-center gap-1 border-r pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isBulletList && 'bg-accent',
                        )}
                        title="Bullet List"
                    >
                        <List className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isOrderedList && 'bg-accent',
                        )}
                        title="Numbered List"
                    >
                        <ListOrdered className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().toggleBlockquote().run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isBlockquote && 'bg-accent',
                        )}
                        title="Quote"
                    >
                        <Quote className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().setHorizontalRule().run()
                        }
                        className="h-8 w-8 p-0"
                        title="Horizontal Line"
                    >
                        <Minus className="h-4 w-4" />
                    </Button>
                </div>

                {/* Text Alignment Group */}
                <div className="flex items-center gap-1 border-r pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().setTextAlign('left').run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isAlignLeft && 'bg-accent',
                        )}
                        title="Align Left"
                    >
                        <AlignLeft className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().setTextAlign('center').run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isAlignCenter && 'bg-accent',
                        )}
                        title="Align Center"
                    >
                        <AlignCenter className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().setTextAlign('right').run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isAlignRight && 'bg-accent',
                        )}
                        title="Align Right"
                    >
                        <AlignRight className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().setTextAlign('justify').run()
                        }
                        className={cn(
                            'h-8 w-8 p-0',
                            editorState.isAlignJustify && 'bg-accent',
                        )}
                        title="Justify"
                    >
                        <AlignJustify className="h-4 w-4" />
                    </Button>
                </div>

                {/* Clear Formatting */}
                <div className="flex items-center gap-1 border-r pr-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            editor.chain().focus().unsetAllMarks().run()
                        }
                        className="h-8 w-8 p-0"
                        title="Clear Formatting"
                    >
                        <RemoveFormatting className="h-4 w-4" />
                    </Button>
                </div>

                {/* Undo/Redo Group */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={!editorState.canUndo}
                        className="h-8 w-8 p-0"
                        title="Undo"
                    >
                        <Undo2 className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={!editorState.canRedo}
                        className="h-8 w-8 p-0"
                        title="Redo"
                    >
                        <Redo2 className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

export function RichTextEditor({
    value,
    onCommit,
    disabled = false,
    size = 'default',
    className,
    placeholder = 'Enter text...',
    characterLimit = 1000,
    hideBorder = false,
    disableDragHandle = false,
}: RichTextEditorProps) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            TextStyleKit,
            Placeholder.configure({ placeholder }),
            CharacterCount.configure({ limit: characterLimit }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
        ],
        content: value || '',
        editable: !disabled,
    });

    // Sync external value
    useEffect(() => {
        if (!editor) return;
        if (editor.getHTML() !== value) {
            editor.commands.setContent(value || '');
        }
    }, [value, editor]);

    // Commit on blur
    useEffect(() => {
        if (!editor) return;

        const blurHandler = () => {
            const html = editor.getHTML();
            onCommit(html);
        };

        editor.on('blur', blurHandler);

        return () => {
            editor.off('blur', blurHandler);
        };
    }, [editor, onCommit]);

    // Character count state
    const { charactersCount, wordsCount } = useEditorState({
        editor,
        selector: (context) => ({
            charactersCount: context.editor.storage.characterCount.characters(),
            wordsCount: context.editor.storage.characterCount.words(),
        }),
    });

    if (!editor) return null;

    // const percentage = Math.min(
    //     100,
    //     Math.round((100 / characterLimit) * charactersCount),
    // );

    return (
        <div
            className={cn(
                'relative rounded-md',
                hideBorder ? '' : 'border',
                className,
            )}
        >
            <MenuBar editor={editor} />
            {!disableDragHandle && (
                <DragHandle editor={editor}>
                    <GripVertical className="h-4 w-4 text-muted-foreground" />
                </DragHandle>
            )}

            <EditorContent
                editor={editor}
                className={cn(
                    size === 'compact'
                        ? 'min-h-[36px] p-2 text-base'
                        : 'min-h-[48px] p-3',
                    'focus:outline-none',
                    '[&_.tiptap.ProseMirror]:min-h-[40px]',
                    '[&_.tiptap.ProseMirror]:outline-none',
                    '[&_.tiptap.ProseMirror_.is-editor-empty:first-child::before]:content-[attr(data-placeholder)]',
                    '[&_.tiptap.ProseMirror_.is-editor-empty:first-child::before]:text-muted-foreground',
                    '[&_.tiptap.ProseMirror_.is-editor-empty:first-child::before]:float-left',
                    '[&_.tiptap.ProseMirror_.is-editor-empty:first-child::before]:h-0',
                    '[&_.tiptap.ProseMirror_.is-editor-empty:first-child::before]:pointer-events-none',
                )}
            />

            <div
                className={cn(
                    'flex items-center justify-between text-sm text-muted-foreground',
                    size === 'compact' ? 'px-2 pb-2' : 'px-3 pb-3',
                    charactersCount >= characterLimit ? 'text-red-600' : '',
                )}
            >
                <span>
                    {charactersCount} / {characterLimit} characters
                </span>
                <span>{wordsCount} words</span>
            </div>
        </div>
    );
}
