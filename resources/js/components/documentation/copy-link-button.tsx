import { Copy } from 'lucide-react';
import { useState } from 'react';

interface CopyLinkButtonProps {
    sectionId: string;
}

export function CopyLinkButton({ sectionId }: CopyLinkButtonProps) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        const url = `${window.location.origin}${window.location.pathname}#${sectionId}`;
        navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <button
            onClick={handleCopy}
            className="ml-2 inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted"
            title="Copy link to section"
        >
            <Copy className="h-3 w-3" />
            {copied && <span className="text-primary">Copied!</span>}
        </button>
    );
}
