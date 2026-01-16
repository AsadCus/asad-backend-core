import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import InputError from '@/components/input-error';
import { MultiSelect } from '@/components/multi-select';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AuthLayout from '@/layouts/auth-layout';
import { ages, experiences } from '@/lib/utils';
import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Form, Head, usePage } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface RegisterProps {
    nationalities: { label: string; value: string }[];
    branches: { label: string; value: number }[];
}

interface AppearanceSetting {
    primary_color?: string;
    border_radius?: string;
}

export default function Register({ nationalities, branches }: RegisterProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [selectedAges, setSelectedAges] = useState<string[]>([]);
    const [selectedNationalities, setSelectedNationalities] = useState<
        string[]
    >([]);
    const [selectedExperiences, setSelectedExperiences] = useState<string[]>(
        [],
    );
    const [screenSize, setScreenSize] = useState<
        'mobile' | 'tablet' | 'desktop'
    >('desktop');
    const { appearance } = usePage<
        SharedData & { appearance?: AppearanceSetting }
    >().props;

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const handleResize = () => {
            const width = window.innerWidth;
            if (width < 640) {
                setScreenSize('mobile');
            } else if (width < 1024) {
                setScreenSize('tablet');
            } else {
                setScreenSize('desktop');
            }
        };
        handleResize();
        window.addEventListener('resize', handleResize);
        return () => {
            if (typeof window !== 'undefined') {
                window.removeEventListener('resize', handleResize);
            }
        };
    }, []);

    return (
        <AuthLayout
            title="Create an account"
            description="Enter your details below to create your account"
        >
            <Head title="Register" />
            <Form
                {...RegisteredUserController.store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        required
                                        tabIndex={3}
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Password"
                                        className="pr-10"
                                    />
                                    <InputError message={errors.password} />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute top-2.5 right-2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showPassword ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm Password
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="password_confirmation"
                                        type={
                                            showConfirmPassword
                                                ? 'text'
                                                : 'password'
                                        }
                                        required
                                        tabIndex={4}
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="Confirm password"
                                        className="pr-10"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowConfirmPassword(
                                                !showConfirmPassword,
                                            )
                                        }
                                        className="absolute top-2.5 right-2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showConfirmPassword ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-3 text-sm font-semibold text-muted-foreground">
                                    Looking for maid?
                                </h3>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="age">Age</Label>
                                        <MultiSelect
                                            name="age_preferences"
                                            options={ages}
                                            onValueChange={setSelectedAges}
                                            defaultValue={selectedAges}
                                            maxCount={
                                                screenSize === 'mobile' ? 2 : 0
                                            }
                                            placeholder="Age"
                                            responsive={true}
                                            minWidth="0px"
                                        />
                                        <InputError message={errors.age} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="nationality">
                                            Country
                                        </Label>
                                        <MultiSelect
                                            name="country_preferences"
                                            options={nationalities}
                                            onValueChange={
                                                setSelectedNationalities
                                            }
                                            defaultValue={selectedNationalities}
                                            maxCount={
                                                screenSize === 'mobile' ? 2 : 0
                                            }
                                            placeholder="Country"
                                            responsive={true}
                                            minWidth="0px"
                                        />
                                        <InputError
                                            message={errors.nationality}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="experience">
                                            Experience
                                        </Label>
                                        <MultiSelect
                                            name="experience_preferences"
                                            options={experiences}
                                            onValueChange={
                                                setSelectedExperiences
                                            }
                                            defaultValue={selectedExperiences}
                                            maxCount={
                                                screenSize === 'mobile' ? 2 : 0
                                            }
                                            placeholder="Experience"
                                            responsive={true}
                                            minWidth="0px"
                                        />
                                        <InputError
                                            message={errors.experience}
                                        />
                                    </div>
                                </div>

                                <div className="mt-4 grid gap-2">
                                    <Label htmlFor="branch_id">
                                        Preferred Service Location
                                    </Label>
                                    <Select name="branch_id" defaultValue="">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a branch" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {branches.map((b) => (
                                                <SelectItem
                                                    key={b.value}
                                                    value={String(b.value)}
                                                >
                                                    {b.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.preferred_location}
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                                style={{
                                    background:
                                        appearance?.primary_color || undefined,
                                    borderRadius:
                                        appearance?.border_radius || undefined,
                                }}
                            >
                                {processing && (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                )}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={6}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
