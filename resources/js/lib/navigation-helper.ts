const SCROLL_OFFSET = 200;
const SCROLL_DELAY = 100;

function findScrollParent(element: HTMLElement): HTMLElement | null {
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
}

function scrollToSection(sectionId: string): void {
    const element = document.getElementById(`section-${sectionId}`);
    if (!element) {
        return;
    }

    const scrollParent = findScrollParent(element);

    if (scrollParent) {
        const elementRect = element.getBoundingClientRect();
        const parentRect = scrollParent.getBoundingClientRect();
        const scrollTop =
            elementRect.top - parentRect.top + scrollParent.scrollTop;

        scrollParent.scrollTo({
            top: Math.max(0, scrollTop - SCROLL_OFFSET),
            behavior: 'smooth',
        });

        return;
    }

    const elementRect = element.getBoundingClientRect();
    const absoluteElementTop = elementRect.top + window.pageYOffset;
    const scrollPosition = absoluteElementTop - SCROLL_OFFSET;

    window.scrollTo({
        top: scrollPosition,
        behavior: 'smooth',
    });
}

export function navigateToSection(
    sectionId: string,
    setOpenSections: React.Dispatch<React.SetStateAction<string[]>>,
): void {
    setOpenSections((prev) => {
        if (!prev.includes(sectionId)) {
            return [...prev, sectionId];
        }
        return prev;
    });

    setTimeout(() => {
        scrollToSection(sectionId);
    }, SCROLL_DELAY);
}
