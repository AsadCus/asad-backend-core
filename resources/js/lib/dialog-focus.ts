export function focusFirstDialogFormField(event: Event): void {
    event.preventDefault();

    const dialogContent = event.currentTarget as HTMLElement | null;

    if (!dialogContent) {
        return;
    }

    requestAnimationFrame(() => {
        const firstField = dialogContent.querySelector<HTMLElement>(
            [
                'input:not([type="hidden"]):not([disabled])',
                'textarea:not([disabled])',
                'select:not([disabled])',
                '[role="combobox"]:not([aria-disabled="true"])',
            ].join(', '),
        );

        firstField?.focus();
    });
}

type DialogTabKeyEvent = {
    key: string;
    shiftKey: boolean;
    altKey?: boolean;
    ctrlKey?: boolean;
    metaKey?: boolean;
    preventDefault: () => void;
    currentTarget: EventTarget & HTMLElement;
};

export function handleDialogTabKey(event: DialogTabKeyEvent): void {
    if (event.key !== 'Tab') {
        return;
    }

    if (event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }

    const dialogContent = event.currentTarget as HTMLElement | null;

    if (!dialogContent) {
        return;
    }

    const focusableSelectors = [
        'input:not([type="hidden"]):not([disabled])',
        'textarea:not([disabled])',
        'select:not([disabled])',
        '[role="combobox"]:not([aria-disabled="true"])',
        'button:not([disabled])',
        'a[href]',
        '[contenteditable="true"]',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    const findScrollParent = (element: HTMLElement): HTMLElement | null => {
        let current: HTMLElement | null = element.parentElement;

        while (current) {
            const style = window.getComputedStyle(current);
            const overflowY = style.overflowY;
            if (
                (overflowY === 'auto' || overflowY === 'scroll') &&
                current.scrollHeight > current.clientHeight
            ) {
                return current;
            }
            current = current.parentElement;
        }

        return null;
    };

    const activeElement = document.activeElement as HTMLElement | null;
    const activeScrollParent = activeElement
        ? findScrollParent(activeElement)
        : null;

    const focusableElements = Array.from(
        dialogContent.querySelectorAll<HTMLElement>(focusableSelectors),
    ).filter((element) => {
        if (element.getAttribute('data-radix-focus-guard') !== null) {
            return false;
        }

        if (element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        if (element.hasAttribute('disabled')) {
            return false;
        }

        // Keep only elements that are actually tabbable. This excludes
        // helper controls intentionally removed from tab order (tabIndex=-1).
        if (element.tabIndex < 0) {
            return false;
        }

        if (activeScrollParent && !activeScrollParent.contains(element)) {
            return false;
        }

        return element.getClientRects().length > 0;
    });

    if (focusableElements.length === 0) {
        return;
    }

    const currentIndex = activeElement
        ? focusableElements.indexOf(activeElement)
        : -1;
    const targetIndex = event.shiftKey
        ? currentIndex <= 0
            ? focusableElements.length - 1
            : currentIndex - 1
        : currentIndex === -1 || currentIndex === focusableElements.length - 1
          ? 0
          : currentIndex + 1;

    event.preventDefault();
    focusableElements[targetIndex]?.focus();
}
