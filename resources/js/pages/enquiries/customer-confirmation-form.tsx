import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    availableEnquiries,
    confirm as confirmEnquiry,
    createCustomerGroup,
    listCustomers,
} from '@/routes/enquiries';
import { OptionType } from '@/types';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useForm } from '@inertiajs/react';
import { AlertCircle, GripVertical, Plus, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import {
    CSSProperties,
    FormEvent,
    HTMLAttributes,
    ReactNode,
    useEffect,
    useState,
} from 'react';

interface MemberData {
    _key: string;
    full_name: string;
    email: string;
    contact_number: string;
    nric_number: string;
    address: string;
    is_leader: boolean;
}

interface ConfirmationFormData {
    enquiry_id: number | null;
    members: Omit<MemberData, '_key'>[];
    terms_accepted?: boolean;
}

interface CustomerConfirmationFormProps {
    enquiryId?: number;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    isPublic?: boolean;
    onSuccess?: () => void;
    onCancel?: () => void;
}

type SortableProps = {
    ref: (el: HTMLElement | null) => void;
    style: CSSProperties;
    attributes: HTMLAttributes<HTMLElement>;
    listeners: HTMLAttributes<HTMLElement>;
};

const emptyMember = (isLeader = false): MemberData => ({
    _key: nanoid(),
    full_name: '',
    email: '',
    contact_number: '',
    nric_number: '',
    address: '',
    is_leader: isLeader,
});

interface CustomerOption extends OptionType {
    name: string;
    email: string;
    contact_number: string;
    nric_number: string;
    address: string;
}

export default function CustomerConfirmationForm({
    enquiryId,
    prefillName = '',
    prefillEmail = '',
    prefillContact = '',
    isPublic = false,
    onSuccess,
    onCancel,
}: CustomerConfirmationFormProps) {
    const [members, setMembers] = useState<MemberData[]>([
        {
            _key: nanoid(),
            full_name: prefillName,
            email: prefillEmail,
            contact_number: prefillContact,
            nric_number: '',
            address: '',
            is_leader: true,
        },
    ]);

    // Enquiry selector state (internal only, when no enquiryId prop)
    const [enquiryOptions, setEnquiryOptions] = useState<OptionType[]>([]);
    const [selectedEnquiryId, setSelectedEnquiryId] = useState<number | null>(
        enquiryId ?? null,
    );

    // Customer search state
    const [customerOptions, setCustomerOptions] = useState<CustomerOption[]>(
        [],
    );

    // Load available enquiries and customers on mount (internal only)
    useEffect(() => {
        if (isPublic) return;

        if (!enquiryId) {
            fetch(availableEnquiries().url)
                .then((res) => res.json())
                .then((data) => setEnquiryOptions(data))
                .catch(() => {});
        }

        fetch(listCustomers().url)
            .then((res) => res.json())
            .then((data) => setCustomerOptions(data))
            .catch(() => {});
    }, [isPublic, enquiryId]);

    const { data, setData, post, processing, errors } =
        useForm<ConfirmationFormData>({
            enquiry_id: enquiryId ?? null,
            members: [],
            terms_accepted: !isPublic,
        });

    // Sync members state to form data (strip _key for submission)
    useEffect(() => {
        setData((prev) => ({
            ...prev,
            members: members.map(({ ...rest }) => rest),
            enquiry_id: enquiryId ?? selectedEnquiryId,
        }));
    }, [enquiryId, setData, members, selectedEnquiryId]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const addMember = () => {
        setMembers((prev) => [...prev, emptyMember(false)]);
    };

    const addCustomerAsMember = (customerValue: string | number) => {
        const customer = customerOptions.find(
            (c) => String(c.value) === String(customerValue),
        );
        if (!customer) return;

        // Prevent duplicates by email
        if (members.some((m) => m.email === customer.email)) return;

        setMembers((prev) => [
            ...prev,
            {
                _key: nanoid(),
                full_name: customer.name,
                email: customer.email,
                contact_number: customer.contact_number,
                nric_number: customer.nric_number,
                address: customer.address,
                is_leader: false,
            },
        ]);
    };

    const removeMember = (key: string) => {
        setMembers((prev) => {
            const filtered = prev.filter((m) => m._key !== key);
            // If removed member was leader and there are remaining members, assign first as leader
            if (!filtered.some((m) => m.is_leader) && filtered.length > 0) {
                filtered[0] = { ...filtered[0], is_leader: true };
            }
            return filtered;
        });
    };

    const updateMember = (
        key: string,
        field: keyof Omit<MemberData, '_key'>,
        value: string | boolean,
    ) => {
        setMembers((prev) =>
            prev.map((m) => {
                if (m._key !== key) {
                    // If setting a new leader, unset others
                    if (field === 'is_leader' && value === true) {
                        return { ...m, is_leader: false };
                    }
                    return m;
                }
                return { ...m, [field]: value };
            }),
        );
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const from = members.findIndex((m) => m._key === active.id);
        const to = members.findIndex((m) => m._key === over.id);
        if (from === -1 || to === -1) return;

        const next = [...members];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);

        setMembers(next);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        // Determine the effective enquiry ID (prop or selector)
        const effectiveEnquiryId = enquiryId ?? selectedEnquiryId;

        // Use confirm endpoint when enquiryId prop provided, otherwise createCustomerGroup
        const submitUrl = enquiryId
            ? confirmEnquiry(enquiryId).url
            : createCustomerGroup().url;

        // Sync the selected enquiry ID into form data before submit
        const membersPayload = members.map(({ ...rest }) => rest);

        post(submitUrl, {
            data: {
                enquiry_id: effectiveEnquiryId,
                members: membersPayload,
                terms_accepted: isPublic ? data.terms_accepted : true,
            },
            onSuccess: () => {
                onSuccess?.();
            },
        });
    };

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];
        if (!message) return null;
        return <p className="mt-1 text-xs text-red-500">{message}</p>;
    };

    const hasErrors = Object.keys(errors).length > 0;

    function SortableRow({
        id,
        children,
    }: {
        id: string;
        children: (props: SortableProps) => ReactNode;
    }) {
        const { setNodeRef, attributes, listeners, transform, transition } =
            useSortable({ id });

        return children({
            ref: setNodeRef,
            style: {
                transform: CSS.Transform.toString(transform),
                transition,
            },
            attributes: attributes as HTMLAttributes<HTMLElement>,
            listeners: listeners as HTMLAttributes<HTMLElement>,
        });
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {hasErrors && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the errors below before submitting.
                    </AlertDescription>
                </Alert>
            )}

            {/* Enquiry Selector (internal only, when no enquiryId prop) */}
            {!isPublic && !enquiryId && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Link to Enquiry (Optional)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ProperInputSelect
                            options={enquiryOptions}
                            value={selectedEnquiryId ?? ''}
                            onValueChange={(v) =>
                                setSelectedEnquiryId(v ? Number(v) : null)
                            }
                            placeholder="Select a confirmed enquiry..."
                        />
                        {renderError('enquiry_id')}
                        <p className="mt-1 text-xs text-muted-foreground">
                            Only confirmed enquiries without a group are listed.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Members Section */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-base">
                            Group Members ({members.length})
                        </CardTitle>
                        <div className="flex gap-2">
                            {!isPublic && customerOptions.length > 0 && (
                                <ProperInputSelect
                                    options={customerOptions.filter(
                                        (c) =>
                                            !members.some(
                                                (m) => m.email === c.email,
                                            ),
                                    )}
                                    value=""
                                    onValueChange={(v) => {
                                        if (v) addCustomerAsMember(v);
                                    }}
                                    placeholder="Search customer..."
                                    className="w-[300px]"
                                    truncate={10}
                                />
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addMember}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Add Member
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {renderError('members')}

                    {members.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No members added. Click &quot;Add Member&quot; to
                            start building the group.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext
                                    items={members.map((m) => m._key)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-10" />
                                                <TableHead className="w-16 text-center">
                                                    Leader
                                                </TableHead>
                                                <TableHead>Full Name</TableHead>
                                                <TableHead>Email</TableHead>
                                                <TableHead>Contact</TableHead>
                                                <TableHead>NRIC</TableHead>
                                                <TableHead>Address</TableHead>
                                                <TableHead className="w-10" />
                                            </TableRow>
                                        </TableHeader>

                                        <TableBody>
                                            {members.map((member, index) => (
                                                <SortableRow
                                                    key={member._key}
                                                    id={member._key}
                                                >
                                                    {({
                                                        ref,
                                                        style,
                                                        attributes,
                                                        listeners,
                                                    }) => (
                                                        <TableRow
                                                            ref={ref}
                                                            style={style}
                                                        >
                                                            {/* Drag Handle */}
                                                            <TableCell className="cursor-grab px-2">
                                                                <div
                                                                    {...attributes}
                                                                    {...listeners}
                                                                >
                                                                    <GripVertical className="h-4 w-4 text-muted-foreground" />
                                                                </div>
                                                            </TableCell>

                                                            {/* Leader Radio */}
                                                            <TableCell className="text-center">
                                                                <input
                                                                    type="radio"
                                                                    name="leader"
                                                                    checked={
                                                                        member.is_leader
                                                                    }
                                                                    onChange={() =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'is_leader',
                                                                            true,
                                                                        )
                                                                    }
                                                                    className="h-4 w-4 accent-primary"
                                                                />
                                                            </TableCell>

                                                            {/* Full Name */}
                                                            <TableCell>
                                                                <ProperInput
                                                                    value={
                                                                        member.full_name
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'full_name',
                                                                            v,
                                                                        )
                                                                    }
                                                                    placeholder="Full name"
                                                                    className="min-w-[140px]"
                                                                />
                                                                {renderError(
                                                                    `members.${index}.full_name`,
                                                                )}
                                                            </TableCell>

                                                            {/* Email */}
                                                            <TableCell>
                                                                <ProperInput
                                                                    value={
                                                                        member.email
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'email',
                                                                            v,
                                                                        )
                                                                    }
                                                                    placeholder="Email"
                                                                    className="min-w-[160px]"
                                                                />
                                                                {renderError(
                                                                    `members.${index}.email`,
                                                                )}
                                                            </TableCell>

                                                            {/* Contact */}
                                                            <TableCell>
                                                                <ProperInput
                                                                    value={
                                                                        member.contact_number
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'contact_number',
                                                                            v,
                                                                        )
                                                                    }
                                                                    placeholder="Contact"
                                                                    className="min-w-[120px]"
                                                                />
                                                                {renderError(
                                                                    `members.${index}.contact_number`,
                                                                )}
                                                            </TableCell>

                                                            {/* NRIC */}
                                                            <TableCell>
                                                                <ProperInput
                                                                    value={
                                                                        member.nric_number
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'nric_number',
                                                                            v,
                                                                        )
                                                                    }
                                                                    placeholder="NRIC"
                                                                    className="min-w-[130px]"
                                                                />
                                                                {renderError(
                                                                    `members.${index}.nric_number`,
                                                                )}
                                                            </TableCell>

                                                            {/* Address */}
                                                            <TableCell>
                                                                <ProperInput
                                                                    value={
                                                                        member.address
                                                                    }
                                                                    onCommit={(
                                                                        v,
                                                                    ) =>
                                                                        updateMember(
                                                                            member._key,
                                                                            'address',
                                                                            v,
                                                                        )
                                                                    }
                                                                    placeholder="Address"
                                                                    className="min-w-[160px]"
                                                                />
                                                                {renderError(
                                                                    `members.${index}.address`,
                                                                )}
                                                            </TableCell>

                                                            {/* Delete */}
                                                            <TableCell className="px-2">
                                                                {members.length >
                                                                    1 && (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            removeMember(
                                                                                member._key,
                                                                            )
                                                                        }
                                                                        className="h-8 w-8 p-0 text-red-500 hover:text-red-700"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </SortableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </SortableContext>
                            </DndContext>
                        </div>
                    )}

                    <p className="mt-2 text-xs text-muted-foreground">
                        Select the radio button to designate the group leader.
                        Drag rows to reorder members.
                    </p>
                </CardContent>
            </Card>

            {/* Terms & Conditions (public only) */}
            {isPublic && (
                <Card>
                    <CardContent>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="terms_accepted"
                                checked={data.terms_accepted}
                                onCheckedChange={(checked) =>
                                    setData('terms_accepted', checked === true)
                                }
                            />
                            <div>
                                <Label
                                    htmlFor="terms_accepted"
                                    className="cursor-pointer text-sm"
                                >
                                    I agree to the Terms and Conditions{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    By checking this box, you confirm that all
                                    the information provided is accurate and you
                                    agree to our terms of service.
                                </p>
                            </div>
                        </div>
                        {renderError('terms_accepted')}
                    </CardContent>
                </Card>
            )}

            {/* Actions */}
            <div className="flex items-center justify-end gap-3">
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {processing
                        ? 'Submitting...'
                        : enquiryId
                          ? 'Confirm Enquiry'
                          : 'Create Customer Group'}
                </Button>
            </div>
        </form>
    );
}
