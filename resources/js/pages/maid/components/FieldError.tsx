import { PropsWithChildren } from 'react';

type FieldErrorProps = PropsWithChildren<{
    message?: string;
}>;

export function FieldError({ message }: FieldErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <p className="absolute -bottom-4 left-0 text-sm text-red-500">
            {message}
        </p>
    );
}
