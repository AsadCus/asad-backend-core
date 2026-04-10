import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';

type TextLinkBaseProps = {
    className?: string;
    children: React.ReactNode;
};

type TextLinkLinkProps = TextLinkBaseProps &
    ComponentProps<typeof Link> & {
        as?: 'link';
    };

type TextLinkButtonProps = TextLinkBaseProps &
    ComponentProps<'button'> & {
        as: 'button';
    };

type TextLinkProps = TextLinkLinkProps | TextLinkButtonProps;

export default function TextLink({
    as = 'link',
    className = '',
    children,
    ...props
}: TextLinkProps) {
    const sharedClassName = cn(
        'text-foreground underline decoration-neutral-300 underline-offset-2 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500',
        className,
    );

    if (as === 'button') {
        const { type = 'button', ...buttonProps } =
            props as TextLinkButtonProps;

        return (
            <button type={type} className={sharedClassName} {...buttonProps}>
                {children}
            </button>
        );
    }

    return (
        <Link className={sharedClassName} {...(props as TextLinkLinkProps)}>
            {children}
        </Link>
    );
}
