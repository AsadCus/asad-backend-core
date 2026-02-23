import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff, RefreshCcw } from 'lucide-react';
import { useState } from 'react';

interface UserPasswordFieldsProps {
    password: string;
    passwordConfirmation: string;
    sendEmail: boolean;
    isView: boolean;
    onPasswordChange: (value: string) => void;
    onPasswordConfirmationChange: (value: string) => void;
    onSendEmailChange: (checked: boolean) => void;
    onGenerateRandom: () => void;
    passwordError?: string;
    passwordConfirmationError?: string;
}

export function UserPasswordFields({
    password,
    passwordConfirmation,
    sendEmail,
    isView,
    onPasswordChange,
    onPasswordConfirmationChange,
    onSendEmailChange,
    onGenerateRandom,
    passwordError,
    passwordConfirmationError,
}: UserPasswordFieldsProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-xl font-semibold">Password & Access</h3>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onGenerateRandom}
                    className="flex w-fit items-center gap-1"
                >
                    <RefreshCcw size={14} />
                    Generate Random
                </Button>
            </div>

            <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                <FormField
                    label="Password"
                    htmlFor="password"
                    error={passwordError}
                >
                    <div className="relative">
                        <Input
                            type={showPassword ? 'text' : 'password'}
                            id="password"
                            value={password}
                            onChange={(event) =>
                                onPasswordChange(event.target.value)
                            }
                            placeholder="Password"
                            disabled={isView}
                            className="pr-10"
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute top-2.5 right-2 text-gray-500 hover:text-gray-700"
                        >
                            {showPassword ? (
                                <EyeOff size={18} />
                            ) : (
                                <Eye size={18} />
                            )}
                        </button>
                    </div>
                </FormField>

                <FormField
                    label="Confirm Password"
                    htmlFor="password_confirmation"
                    error={passwordConfirmationError}
                >
                    <div className="relative">
                        <Input
                            type={showConfirmPassword ? 'text' : 'password'}
                            id="password_confirmation"
                            value={passwordConfirmation}
                            onChange={(event) =>
                                onPasswordConfirmationChange(event.target.value)
                            }
                            placeholder="Confirm Password"
                            disabled={isView}
                            className="pr-10"
                        />
                        <button
                            type="button"
                            onClick={() =>
                                setShowConfirmPassword(!showConfirmPassword)
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
                </FormField>
            </div>

            <div className="flex items-center gap-2">
                <input
                    id="send_email"
                    type="checkbox"
                    checked={sendEmail}
                    onChange={(event) =>
                        onSendEmailChange(event.target.checked)
                    }
                    disabled={isView}
                    aria-label="Send email access to this user"
                />
                <Label htmlFor="send_email">
                    Send email access to this user (optional)
                </Label>
            </div>
        </div>
    );
}
