import { FormEventHandler, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Transition } from '@headlessui/react';
import { update as updateReportTemplate } from '@/routes/report-template';

interface FormData {
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    footer_text: string;
    logo_file: File | null;
    stamp_file: File | null;
    signature_file: File | null;
}

interface ReportTemplateData {
    settings: {
        company_name: string;
        company_address: string;
        company_phone: string;
        company_email: string;
        footer_text: string;
        logo_path: string | null;
        stamp_path: string | null;
        signature_path: string | null;
    };
}

export default function ReportTemplate({ settings }: ReportTemplateData) {
    const { data, setData, post, errors, processing, recentlySuccessful, transform } =
        useForm<FormData>({
            company_name: settings.company_name,
            company_address: settings.company_address || '',
            company_phone: settings.company_phone || '',
            company_email: settings.company_email || '',
            footer_text: settings.footer_text || '',
            logo_file: null,
            stamp_file: null,
            signature_file: null,
        });

    const [logoPreview, setLogoPreview] = useState<string | null>(
        settings.logo_path ? `/storage/${settings.logo_path}` : null
    );
    const [stampPreview, setStampPreview] = useState<string | null>(
        settings.stamp_path ? `/storage/${settings.stamp_path}` : null
    );
    const [signaturePreview, setSignaturePreview] = useState<string | null>(
        settings.signature_path ? `/storage/${settings.signature_path}` : null
    );

    const handleFileChange = (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreview: (preview: string | null) => void
    ) => {
        return (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (file) {
                setData(field, file);
                const reader = new FileReader();
                reader.onloadend = () => {
                    setPreview(reader.result as string);
                };
                reader.readAsDataURL(file);
            }
        };
    };

    const clearFile = (
        field: 'logo_file' | 'stamp_file' | 'signature_file',
        setPreview: (preview: string | null) => void
    ) => {
        return () => {
            setData(field, null);
            setPreview(null);
        };
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((data) => ({
            ...data,
            _method: 'put',
        }));
        post(updateReportTemplate.url(), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Report Template Settings" />

            <SettingsLayout>
                <HeadingSmall
                    title="Report Template Settings"
                    description="Manage branding for invoices, quotations, and receipts"
                />

                <form onSubmit={submit} className="mt-6 space-y-6">
                    {/* Company Name */}
                    <div>
                        <Label htmlFor="company_name">
                            Company Name <span className="text-red-500">*</span>
                        </Label>
                        <Input
                            id="company_name"
                            type="text"
                            value={data.company_name}
                            onChange={(e) =>
                                setData('company_name', e.target.value)
                            }
                            className="mt-1 block w-full"
                            autoComplete="organization"
                            required
                        />
                        <InputError message={errors.company_name} className="mt-2" />
                    </div>

                    {/* Company Address */}
                    <div>
                        <Label htmlFor="company_address">Company Address</Label>
                        <Textarea
                            id="company_address"
                            value={data.company_address}
                            onChange={(e) =>
                                setData('company_address', e.target.value)
                            }
                            className="mt-1 block w-full"
                            rows={3}
                        />
                        <InputError message={errors.company_address} className="mt-2" />
                    </div>

                    {/* Company Phone */}
                    <div>
                        <Label htmlFor="company_phone">Company Phone</Label>
                        <Input
                            id="company_phone"
                            type="text"
                            value={data.company_phone}
                            onChange={(e) =>
                                setData('company_phone', e.target.value)
                            }
                            className="mt-1 block w-full"
                            autoComplete="tel"
                        />
                        <InputError message={errors.company_phone} className="mt-2" />
                    </div>

                    {/* Company Email */}
                    <div>
                        <Label htmlFor="company_email">Company Email</Label>
                        <Input
                            id="company_email"
                            type="email"
                            value={data.company_email}
                            onChange={(e) =>
                                setData('company_email', e.target.value)
                            }
                            className="mt-1 block w-full"
                            autoComplete="email"
                        />
                        <InputError message={errors.company_email} className="mt-2" />
                    </div>

                    {/* Footer Text */}
                    <div>
                        <Label htmlFor="footer_text">Footer Text</Label>
                        <Textarea
                            id="footer_text"
                            value={data.footer_text}
                            onChange={(e) =>
                                setData('footer_text', e.target.value)
                            }
                            className="mt-1 block w-full"
                            rows={2}
                        />
                        <InputError message={errors.footer_text} className="mt-2" />
                    </div>

                    {/* Logo Upload */}
                    <div>
                        <Label htmlFor="logo_file">Company Logo</Label>
                        <Input
                            id="logo_file"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg"
                            onChange={handleFileChange('logo_file', setLogoPreview)}
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-sm text-gray-500">
                            Accepted: JPG, JPEG, PNG. Max 2MB
                        </p>
                        <InputError message={errors.logo_file} className="mt-2" />
                        {logoPreview && (
                            <div className="mt-4">
                                <img
                                    src={logoPreview}
                                    alt="Logo Preview"
                                    className="h-32 w-auto object-contain border rounded"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFile('logo_file', setLogoPreview)}
                                    className="mt-2"
                                >
                                    Clear Logo
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Stamp Upload */}
                    <div>
                        <Label htmlFor="stamp_file">Company Stamp</Label>
                        <Input
                            id="stamp_file"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg"
                            onChange={handleFileChange('stamp_file', setStampPreview)}
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-sm text-gray-500">
                            Accepted: JPG, JPEG, PNG. Max 2MB
                        </p>
                        <InputError message={errors.stamp_file} className="mt-2" />
                        {stampPreview && (
                            <div className="mt-4">
                                <img
                                    src={stampPreview}
                                    alt="Stamp Preview"
                                    className="h-32 w-auto object-contain border rounded"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFile('stamp_file', setStampPreview)}
                                    className="mt-2"
                                >
                                    Clear Stamp
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Signature Upload */}
                    <div>
                        <Label htmlFor="signature_file">Authorized Signature</Label>
                        <Input
                            id="signature_file"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg"
                            onChange={handleFileChange(
                                'signature_file',
                                setSignaturePreview
                            )}
                            className="mt-1 block w-full"
                        />
                        <p className="mt-1 text-sm text-gray-500">
                            Accepted: JPG, JPEG, PNG. Max 2MB
                        </p>
                        <InputError message={errors.signature_file} className="mt-2" />
                        {signaturePreview && (
                            <div className="mt-4">
                                <img
                                    src={signaturePreview}
                                    alt="Signature Preview"
                                    className="h-32 w-auto object-contain border rounded"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFile(
                                        'signature_file',
                                        setSignaturePreview
                                    )}
                                    className="mt-2"
                                >
                                    Clear Signature
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Submit Button */}
                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-gray-600">Saved successfully.</p>
                        </Transition>
                    </div>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
