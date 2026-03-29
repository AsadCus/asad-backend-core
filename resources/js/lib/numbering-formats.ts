export interface NumberingFormatRecord {
    id: number;
    model_key: string;
    name: string;
    increment_padding: number;
    increment_start: number;
    increment_scope: 'format' | 'model';
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
}

export interface NumberingFormatPayload {
    model_key: string;
    name: string;
    increment_padding?: number;
    increment_start?: number;
    increment_scope?: 'format' | 'model';
    is_default?: boolean;
    is_active?: boolean;
    sort_order?: number;
}

export interface NumberSuggestion {
    model_key: string;
    format_id: number;
    number: string;
    next_increment: number;
}

const getCsrfToken = (): string => {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
};

async function parseResponse<T>(response: Response): Promise<T> {
    const payload = await response.json().catch(() => null);

    if (response.ok) {
        if (payload === null && response.status === 204) {
            return {} as T;
        }

        if (payload === null) {
            return {} as T;
        }

        return payload as T;
    }

    const firstValidationError =
        payload && typeof payload === 'object' && payload !== null
            ? Object.values(
                  (payload as { errors?: Record<string, string[]> }).errors ??
                      {},
              )
                  .flat()
                  .find((message) => typeof message === 'string')
            : null;

    const message =
        (payload as { message?: string; error?: string } | null)?.message ??
        (payload as { message?: string; error?: string } | null)?.error ??
        firstValidationError ??
        response.statusText ??
        'Failed to complete numbering format request.';

    throw new Error(String(message));
}

export async function fetchSupportedModelKeys(): Promise<string[]> {
    const response = await fetch('/numbering-formats', {
        credentials: 'same-origin',
    });

    const payload = await parseResponse<{ supported_model_keys?: string[] }>(
        response,
    );

    return payload.supported_model_keys ?? [];
}

export async function fetchFormats(
    modelKey: string,
): Promise<NumberingFormatRecord[]> {
    const query = new URLSearchParams({ model_key: modelKey });

    const response = await fetch(`/numbering-formats?${query.toString()}`, {
        credentials: 'same-origin',
    });

    const payload = await parseResponse<{ formats?: NumberingFormatRecord[] }>(
        response,
    );

    return payload.formats ?? [];
}

export async function suggestNumber(
    modelKey: string,
    formatId?: number | null,
): Promise<NumberSuggestion> {
    const query = new URLSearchParams({ model_key: modelKey });

    if (formatId) {
        query.set('format_id', String(formatId));
    }

    const response = await fetch(
        `/numbering-formats/suggest?${query.toString()}`,
        {
            credentials: 'same-origin',
        },
    );

    return parseResponse<NumberSuggestion>(response);
}

export async function createFormat(
    payload: NumberingFormatPayload,
): Promise<NumberingFormatRecord> {
    const response = await fetch('/numbering-formats', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    });

    return parseResponse<NumberingFormatRecord>(response);
}

export async function updateFormat(
    id: number,
    payload: NumberingFormatPayload,
): Promise<NumberingFormatRecord> {
    const response = await fetch(`/numbering-formats/${id}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    });

    return parseResponse<NumberingFormatRecord>(response);
}

export async function deleteFormat(id: number): Promise<void> {
    const response = await fetch(`/numbering-formats/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
        },
    });

    await parseResponse<{ deleted?: boolean }>(response);
}
