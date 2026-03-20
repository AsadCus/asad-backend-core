export function convertNameToArabic(name: string): string {
    const source = name.trim().toLowerCase();

    if (source === '') {
        return '';
    }

    const digraphs: Array<[string, string]> = [
        ['sh', 'ش'],
        ['kh', 'خ'],
        ['th', 'ث'],
        ['dh', 'ذ'],
        ['gh', 'غ'],
        ['ph', 'ف'],
        ['ch', 'تش'],
        ['sy', 'ش'],
        ['ny', 'ني'],
    ];

    let normalized = source;

    digraphs.forEach(([latin, arabic]) => {
        normalized = normalized.replaceAll(latin, ` ${arabic} `);
    });

    const map: Record<string, string> = {
        a: 'ا',
        b: 'ب',
        c: 'ك',
        d: 'د',
        e: 'ي',
        f: 'ف',
        g: 'ج',
        h: 'ه',
        i: 'ي',
        j: 'ج',
        k: 'ك',
        l: 'ل',
        m: 'م',
        n: 'ن',
        o: 'و',
        p: 'ف',
        q: 'ق',
        r: 'ر',
        s: 'س',
        t: 'ت',
        u: 'و',
        v: 'ف',
        w: 'و',
        x: 'كس',
        y: 'ي',
        z: 'ز',
    };

    const tokens = normalized
        .split(/\s+/)
        .filter((token) => token.length > 0)
        .map((token) => {
            if (/^[\u0600-\u06FF]+$/u.test(token)) {
                return token;
            }

            return token
                .split('')
                .map((char) => map[char] ?? char)
                .join('');
        });

    return tokens.join(' ').trim();
}

export function filterArabicInput(value: string): string {
    return value
        .replace(/[^\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\s]/g, '')
        .replace(/\s{2,}/g, ' ');
}

export function normalizeArabicNameInput(value: string): string {
    if (value.trim() === '') {
        return value.replace(/[^\s]/g, '');
    }

    const trailingSpaces = (value.match(/\s+$/) ?? [''])[0];
    const converted = convertNameToArabic(value);

    return filterArabicInput(`${converted}${trailingSpaces}`);
}
