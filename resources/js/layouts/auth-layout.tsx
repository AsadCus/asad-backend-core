// import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import AuthSplitLayout from './auth/auth-split-layout';

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <AuthSplitLayout title={title} description={description} {...props}>
            {children}
        </AuthSplitLayout>
    );
}
