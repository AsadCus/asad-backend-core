import {
    SCROLL_DELAY,
    SCROLL_OFFSET,
} from '../pages/maid/constants/formConfig';

function scrollToSection(sectionId: string): void {
    const element = document.getElementById(`section-${sectionId}`);
    if (element) {
        const elementRect = element.getBoundingClientRect();
        const absoluteElementTop = elementRect.top + window.pageYOffset;
        const scrollPosition = absoluteElementTop - SCROLL_OFFSET;

        window.scrollTo({
            top: scrollPosition,
            behavior: 'smooth',
        });
    }
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
