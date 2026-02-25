import { DatePickerField } from '@/components/date-picker';
import { FieldRequirements } from '@/components/field-requirements';
import { ProperInput } from '@/components/proper-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/manifests';
import { type ValueNumberOptionType } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Minus, Plus, RotateCcw } from 'lucide-react';
import { FormEvent, useCallback, useState } from 'react';
import { type ManifestSchema, type TravelerSchema } from './schema';
import { manifestValidationSchema } from './validation';

interface CustomerGroupData {
    id: number;
    package_room_type: string;
    enquiry_id: number;
    enquiry_type: string;
    enquiry_status: string;
    leader_name: string;
    leader_email: string;
    leader_contact: string;
    leader_customer_number: string;
    member_count: number;
    created_at: string;
    members: CustomerMemberData[];
}

interface CustomerMemberData {
    id?: number;
    customer_id?: number;
    is_leader?: boolean;
    name?: string;
    email?: string;
    contact?: string;
    customer_number?: string;
    nric_number?: string;
    sn?: number;
    name_as_per_passport?: string;
    date_of_sign_up?: string;
    is_first_time_umrah?: boolean;
    ppt_no?: string;
    passport_no?: string;
    gender?: string;
    date_of_birth?: string;
    age?: number;
    contact_no?: string;
    date_of_issue?: string;
    date_of_expiry?: string;
    issue_place?: string;
    birth_place?: string;
    package_price?: number;
    discount?: number;
    date_of_deposit_payment?: string;
    deposit_payment?: number;
    date_of_second_payment?: string;
    second_payment?: number;
    balance_due?: number;
    is_fully_paid?: boolean;
    receipt_no?: string;
    remarks?: string;
    nationality?: string;
    room_no?: string;
    room_type?: string;
    bed_type?: string;
    no_of_beds_checked?: number;
    meal?: string;
    relationship?: string;
    passport_number?: string;
    passport_issue_date?: string;
    passport_expiry_date?: string;
    passport_place_of_issue?: string;
}

export type ManifestFormData = Omit<ManifestSchema, 'travelers' | 'rooms'> & {
    travelers?: Record<number, TravelerSchema[]>;
    roomListMakkah?: Record<number, TravelerSchema[]>;
    roomListMadinah?: Record<number, TravelerSchema[]>;
    roomListOthers?: Record<number, TravelerSchema[]>;
    airlineList?: Record<number, TravelerSchema[]>;
    rooms?: Record<number, TravelerSchema[]>[];
};

interface ManifestFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: ManifestFormData;
    dataPackage?: ValueNumberOptionType[];
    customerGroups?: CustomerGroupData[];
    onCancel: () => void;
}

const STATUS_OPTIONS = ['draft', 'confirmed', 'completed', 'cancelled'];

const MEAL_OPTIONS = ['Breakfast', 'Lunch', 'Dinner', 'None'];

const ROOM_TYPE_OPTIONS = ['Quad', 'Triple', 'Double', 'Single'];

const BED_TYPE_OPTIONS = ['Single', 'King'];

const GENDER_OPTIONS = ['male', 'female', 'other'];

// ============ Default Values ============

const getDefaultValues = (
    initialData: ManifestFormData | undefined,
): { data: ManifestFormData; selectedGroups: number[] } => {
    return {
        data: initialData || {},
        selectedGroups: initialData?.travelers
            ? Object.keys(initialData.travelers).map(Number)
            : [],
    };
};

// ============ Sub-Components ============

interface ManifestInfoCardProps {
    isView: boolean;
    data: ManifestFormData;
    setData: (key: string, value: ManifestFormData[keyof ManifestFormData]) => void;
    dataPackage: ValueNumberOptionType[];
    renderError: (path: string) => React.ReactNode;
}

function ManifestInfoCard({
    isView,
    data,
    setData,
    dataPackage,
    renderError,
}: ManifestInfoCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Manifest Information</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="package_id">
                        Package
                        <FieldRequirements
                            required
                            hint="Select the package for this manifest"
                        />
                    </Label>
                    <div className="relative">
                        <Select
                            value={String(data.package_id || '')}
                            onValueChange={(v) =>
                                setData('package_id', Number(v))
                            }
                            disabled={isView}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Package" />
                            </SelectTrigger>
                            <SelectContent>
                                {dataPackage.map((pkg) => (
                                    <SelectItem
                                        key={pkg.value}
                                        value={String(pkg.value)}
                                    >
                                        {pkg.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('package_id')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="reference_number">
                        Reference Number
                        <FieldRequirements
                            required
                            hint="Enter the manifest reference number"
                        />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="reference_number"
                            value={data.reference_number ?? ''}
                            disabled={isView}
                            onCommit={(v) => setData('reference_number', v)}
                            placeholder="Enter reference number"
                        />
                        {renderError('reference_number')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="status">
                        Status
                        <FieldRequirements hint="Select the manifest status" />
                    </Label>
                    <div className="relative">
                        <Select
                            value={data.status}
                            onValueChange={(v) => setData('status', v)}
                            disabled={isView}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {s.charAt(0).toUpperCase() + s.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('status')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="duration">
                        Duration
                        <FieldRequirements hint="Enter duration (e.g. 14 Days / 13 Nights)" />
                    </Label>
                    <div className="relative">
                        <ProperInput
                            id="duration"
                            value={data.duration ?? ''}
                            disabled={isView}
                            onCommit={(v) => setData('duration', v)}
                            placeholder="e.g. 14 Days / 13 Nights"
                        />
                        {renderError('duration')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="departure_date">
                        Departure Date
                        <FieldRequirements
                            required
                            hint="Select departure date"
                        />
                    </Label>
                    <div className="relative">
                        <DatePickerField
                            id="departure_date"
                            value={data.departure_date}
                            onChange={(v) => setData('departure_date', v)}
                            disabled={isView}
                        />
                        {renderError('departure_date')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="return_date">
                        Return Date
                        <FieldRequirements required hint="Select return date" />
                    </Label>
                    <div className="relative">
                        <DatePickerField
                            id="return_date"
                            value={data.return_date}
                            onChange={(v) => setData('return_date', v)}
                            disabled={isView}
                        />
                        {renderError('return_date')}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface HotelDetailsCardProps {
    isView: boolean;
    data: ManifestFormData;
    setData: (key: string, value: ManifestFormData[keyof ManifestFormData]) => void;
    renderError: (path: string) => React.ReactNode;
}

function HotelDetailsCard({
    isView,
    data,
    setData,
    renderError,
}: HotelDetailsCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Hotel Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Makkah */}
                <div>
                    <h4 className="mb-3 text-base font-medium text-muted-foreground">
                        Makkah Hotel
                    </h4>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="makkah_hotel">
                                Hotel Name
                                <FieldRequirements hint="Enter Makkah hotel name" />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    id="makkah_hotel"
                                    value={data.makkah_hotel ?? ''}
                                    disabled={isView}
                                    onCommit={(v) => setData('makkah_hotel', v)}
                                    placeholder="Enter hotel name"
                                />
                                {renderError('makkah_hotel')}
                            </div>
                        </div>

                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="makkah_check_in">
                                Check In
                                <FieldRequirements hint="Select check-in date" />
                            </Label>
                            <div className="relative">
                                <DatePickerField
                                    id="makkah_check_in"
                                    value={data.makkah_check_in}
                                    onChange={(v) =>
                                        setData('makkah_check_in', v)
                                    }
                                    disabled={isView}
                                />
                                {renderError('makkah_check_in')}
                            </div>
                        </div>

                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="makkah_check_out">
                                Check Out
                                <FieldRequirements hint="Select check-out date" />
                            </Label>
                            <div className="relative">
                                <DatePickerField
                                    id="makkah_check_out"
                                    value={data.makkah_check_out}
                                    onChange={(v) =>
                                        setData('makkah_check_out', v)
                                    }
                                    disabled={isView}
                                />
                                {renderError('makkah_check_out')}
                            </div>
                        </div>
                    </div>
                </div>
                {/* Madinah */}
                <div>
                    <h4 className="mb-3 text-base font-medium text-muted-foreground">
                        Madinah Hotel
                    </h4>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="madinah_hotel">
                                Hotel Name
                                <FieldRequirements hint="Enter Madinah hotel name" />
                            </Label>
                            <div className="relative">
                                <ProperInput
                                    id="madinah_hotel"
                                    value={data.madinah_hotel ?? ''}
                                    disabled={isView}
                                    onCommit={(v) =>
                                        setData('madinah_hotel', v)
                                    }
                                    placeholder="Enter hotel name"
                                />
                                {renderError('madinah_hotel')}
                            </div>
                        </div>

                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="madinah_check_in">
                                Check In
                                <FieldRequirements hint="Select check-in date" />
                            </Label>
                            <div className="relative">
                                <DatePickerField
                                    id="madinah_check_in"
                                    value={data.madinah_check_in}
                                    onChange={(v) =>
                                        setData('madinah_check_in', v)
                                    }
                                    disabled={isView}
                                />
                                {renderError('madinah_check_in')}
                            </div>
                        </div>

                        <div className="grid w-full items-center gap-3">
                            <Label htmlFor="madinah_check_out">
                                Check Out
                                <FieldRequirements hint="Select check-out date" />
                            </Label>
                            <div className="relative">
                                <DatePickerField
                                    id="madinah_check_out"
                                    value={data.madinah_check_out}
                                    onChange={(v) =>
                                        setData('madinah_check_out', v)
                                    }
                                    disabled={isView}
                                />
                                {renderError('madinah_check_out')}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface MealsNotesCardProps {
    isView: boolean;
    data: ManifestFormData;
    setData: (key: string, value: ManifestFormData[keyof ManifestFormData]) => void;
    renderError: (path: string) => React.ReactNode;
}

function MealsNotesCard({
    isView,
    data,
    setData,
    renderError,
}: MealsNotesCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Meals & Notes</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="first_meal">
                        First Meal
                        <FieldRequirements hint="Select first meal" />
                    </Label>
                    <div className="relative">
                        <Select
                            value={data.first_meal}
                            onValueChange={(v) => setData('first_meal', v)}
                            disabled={isView}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Meal" />
                            </SelectTrigger>
                            <SelectContent>
                                {MEAL_OPTIONS.map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {m}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('first_meal')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="last_meal">
                        Last Meal
                        <FieldRequirements hint="Select last meal" />
                    </Label>
                    <div className="relative">
                        <Select
                            value={data.last_meal}
                            onValueChange={(v) => setData('last_meal', v)}
                            disabled={isView}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select Meal" />
                            </SelectTrigger>
                            <SelectContent>
                                {MEAL_OPTIONS.map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {m}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {renderError('last_meal')}
                    </div>
                </div>

                <div className="grid w-full items-center gap-3 md:col-span-3">
                    <Label htmlFor="notes">
                        Notes
                        <FieldRequirements hint="Add any additional notes" />
                    </Label>
                    <div className="relative">
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            disabled={isView}
                            rows={3}
                        />
                        {renderError('notes')}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface TravelersByGroupCardProps {
    isView: boolean;
    customerGroups: CustomerGroupData[];
    data: ManifestFormData;
    updateTraveler: (
        groupId: number,
        index: number,
        field: keyof TravelerSchema,
        value: string | number | boolean,
    ) => void;
    removeTraveler: (index: number) => void;
    renderError: (path: string) => React.ReactNode;
    selectedGroupIds: number[];
    onAddGroup: (groupId: number) => void;
    onRemoveGroup: (groupId: number) => void;
}

function TravelersByGroupCard({
    isView,
    customerGroups,
    data,
    updateTraveler,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    removeTraveler,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    renderError,
    selectedGroupIds,
    onAddGroup,
    onRemoveGroup,
}: TravelersByGroupCardProps) {
    const [openGroupDialog, setOpenGroupDialog] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    const getGroupLabel = (groupId: number): string => {
        const group = customerGroups.find((g) => g.id === groupId);
        if (!group) return `Group ${groupId}`;
        const leader = group.members?.find((m) => m.is_leader);
        const leaderName = leader?.name || 'Unknown';
        return `Group - ${leaderName}`;
    };

    const getGroupMembers = (groupId: number): CustomerMemberData[] => {
        return (
            (data.travelers?.[groupId] as unknown as CustomerMemberData[]) || []
        );
    };

    const addGroup = (groupId: number) => {
        onAddGroup(groupId);
        setOpenGroupDialog(false);
    };

    const removeGroup = (groupId: number) => {
        onRemoveGroup(groupId);
    };

    const getFilteredGroups = (): CustomerGroupData[] => {
        if (!searchTerm.trim()) return customerGroups;

        return customerGroups.filter((group) => {
            const groupLabel = getGroupLabel(group.id).toLowerCase();
            const memberNames =
                group.members
                    ?.map((m) => (m.name ?? '').toLowerCase())
                    .join(' ') || '';

            return (
                groupLabel.includes(searchTerm.toLowerCase()) ||
                memberNames.includes(searchTerm.toLowerCase())
            );
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Travelers by Group</CardTitle>
                {!isView && (
                    <Dialog
                        open={openGroupDialog}
                        onOpenChange={(open) => {
                            setOpenGroupDialog(open);
                            if (!open) setSearchTerm('');
                        }}
                    >
                        <Button
                            size="sm"
                            onClick={(e) => {
                                e.preventDefault();
                                setOpenGroupDialog(true);
                            }}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Traveler
                        </Button>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Select Customer Group</DialogTitle>
                            </DialogHeader>
                            <div className="space-y-3">
                                <Input
                                    placeholder="Search by group name or member..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    className="mb-2"
                                />
                                <div className="max-h-96 space-y-2 overflow-y-auto">
                                    {getFilteredGroups().length > 0 ? (
                                        getFilteredGroups().map((group) => (
                                            <Button
                                                key={group.id}
                                                variant={
                                                    selectedGroupIds.includes(
                                                        group.id,
                                                    )
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="w-full justify-start"
                                                onClick={() =>
                                                    addGroup(group.id)
                                                }
                                                disabled={selectedGroupIds.includes(
                                                    group.id,
                                                )}
                                            >
                                                {getGroupLabel(group.id)} (
                                                {group.members?.length || 0}{' '}
                                                members)
                                            </Button>
                                        ))
                                    ) : (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            No groups found matching your
                                            search.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </CardHeader>
            <CardContent>
                {selectedGroupIds.length === 0 ? (
                    <p className="py-4 text-center text-base text-muted-foreground">
                        No groups added. Click "Add Travel" to select a group.
                    </p>
                ) : (
                    <Tabs
                        defaultValue={`group-${selectedGroupIds[0]}`}
                        className="w-full"
                    >
                        <TabsList className="flex w-full flex-wrap">
                            {selectedGroupIds.map((groupId) => (
                                <TabsTrigger
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="flex items-center gap-2"
                                >
                                    {getGroupLabel(groupId)}{' '}
                                    {!isView && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-4 w-4 p-0"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                removeGroup(groupId);
                                            }}
                                        >
                                            <Minus className="h-3 w-3" />
                                        </Button>
                                    )}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {selectedGroupIds.map((groupId) => {
                            const members = getGroupMembers(groupId);
                            const groupLabel = getGroupLabel(groupId);

                            return (
                                <TabsContent
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="mt-4 space-y-4"
                                >
                                    <div className="">
                                        <h4 className="mb-4 font-semibold">
                                            {groupLabel} Members
                                        </h4>

                                        {members.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border text-sm">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                S/N
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Name As Per
                                                                Passport
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Date of Sign Up
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                1st Time Umrah
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                PPT No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Gender
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.B
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Age
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Contact No.
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.I
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.E
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Issue Place
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Birth Place
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Package Price
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Discount
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Date of Deposit
                                                                Payment
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Deposit Payment
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Date of Second
                                                                Payment
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Second Payment
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Balance Due
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Fully Paid
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Receipt No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Remarks
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {members.map(
                                                            (
                                                                member,
                                                                memberIdx,
                                                            ) => (
                                                                <tr
                                                                    key={
                                                                        member.id
                                                                    }
                                                                    className="border-b"
                                                                >
                                                                    <td className="p-2">
                                                                        {memberIdx +
                                                                            1}
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.name_as_per_passport ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                v,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'name_as_per_passport',
                                                                                    v
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[150px]"
                                                                        />
                                                                    </td>

                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_sign_up ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_sign_up',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Select
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            value={
                                                                                member.is_first_time_umrah
                                                                                    ? 'yes'
                                                                                    : 'no'
                                                                            }
                                                                            onValueChange={(
                                                                                value,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'is_first_time_umrah',
                                                                                    value ===
                                                                                        'yes',
                                                                                )
                                                                            }
                                                                        >
                                                                            <SelectTrigger className="min-w-[100px]">
                                                                                <SelectValue placeholder="Yes/No" />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem value="yes">
                                                                                    Yes
                                                                                </SelectItem>
                                                                                <SelectItem value="no">
                                                                                    No
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.ppt_no ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'ppt_no',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                            placeholder="PPT No"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Select
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            value={
                                                                                member.gender ||
                                                                                ''
                                                                            }
                                                                            onValueChange={(
                                                                                value,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'gender',
                                                                                    value,
                                                                                )
                                                                            }
                                                                        >
                                                                            <SelectTrigger className="min-w-[100px]">
                                                                                <SelectValue placeholder="Gender" />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                {GENDER_OPTIONS.map(
                                                                                    (
                                                                                        g,
                                                                                    ) => (
                                                                                        <SelectItem
                                                                                            key={
                                                                                                g
                                                                                            }
                                                                                            value={
                                                                                                g
                                                                                            }
                                                                                        >
                                                                                            {
                                                                                                g
                                                                                            }
                                                                                        </SelectItem>
                                                                                    ),
                                                                                )}
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_birth ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_birth',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.age?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'age',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[80px]"
                                                                            placeholder="Age"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.contact_no ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'contact_no',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                            placeholder="Contact"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_issue ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_issue',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_expiry ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_expiry',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.issue_place ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'issue_place',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                            placeholder="Issue Place"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.birth_place ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'birth_place',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                            placeholder="Birth Place"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.package_price?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'package_price',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Price"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.discount?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'discount',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Discount"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_deposit_payment ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_deposit_payment',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.deposit_payment?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'deposit_payment',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Deposit"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            type="date"
                                                                            value={
                                                                                member.date_of_second_payment ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'date_of_second_payment',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[120px]"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.second_payment?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'second_payment',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="2nd Payment"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.balance_due?.toString() ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'balance_due',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Balance"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Select
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            value={
                                                                                member.is_fully_paid
                                                                                    ? 'yes'
                                                                                    : 'no'
                                                                            }
                                                                            onValueChange={(
                                                                                value,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'is_fully_paid',
                                                                                    value ===
                                                                                        'yes',
                                                                                )
                                                                            }
                                                                        >
                                                                            <SelectTrigger className="min-w-[100px]">
                                                                                <SelectValue
                                                                                    placeholder={
                                                                                        member.is_fully_paid
                                                                                            ? 'Yes'
                                                                                            : 'No'
                                                                                    }
                                                                                />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                <SelectItem value="yes">
                                                                                    Yes
                                                                                </SelectItem>
                                                                                <SelectItem value="no">
                                                                                    No
                                                                                </SelectItem>
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.receipt_no ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'receipt_no',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Receipt"
                                                                        />
                                                                    </td>
                                                                    <td className="p-2">
                                                                        <Input
                                                                            value={
                                                                                member.remarks ||
                                                                                ''
                                                                            }
                                                                            disabled={
                                                                                isView
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateTraveler(
                                                                                    groupId,
                                                                                    memberIdx,
                                                                                    'remarks',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="min-w-[100px]"
                                                                            placeholder="Remarks"
                                                                        />
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="py-4 text-center text-base text-muted-foreground">
                                                No members in this group.
                                            </p>
                                        )}
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

interface RoomListMakkahCardProps {
    isView: boolean;
    customerGroups: CustomerGroupData[];
    data: ManifestFormData;
    updateTraveler: (
        groupId: number,
        index: number,
        field: keyof TravelerSchema,
        value: string | number | boolean,
    ) => void;
    removeTraveler: (index: number) => void;
    renderError: (path: string) => React.ReactNode;
    selectedGroupIds: number[];
}

function RoomListMakkahCard({
    isView,
    customerGroups,
    data,
    updateTraveler,
    removeTraveler,
    renderError,
    selectedGroupIds,
}: RoomListMakkahCardProps) {
    const getGroupLabel = (groupId: number): string => {
        const group = customerGroups.find((g) => g.id === groupId);
        if (!group) return `Group ${groupId}`;
        const leader = group.members?.find((m) => m.is_leader);
        const leaderName = leader?.name || 'Unknown';
        return `Group - ${leaderName}`;
    };

    const getGroupMembers = (groupId: number): CustomerMemberData[] => {
        return (
            (data?.roomListMakkah?.[
                groupId
            ] as unknown as CustomerMemberData[]) || []
        );
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Room List Makkah</CardTitle>
            </CardHeader>
            <CardContent>
                {selectedGroupIds.length === 0 ? (
                    <p className="py-4 text-center text-base text-muted-foreground">
                        No groups added. Select a group from the Travelers tab.
                    </p>
                ) : (
                    <Tabs
                        defaultValue={`group-${selectedGroupIds[0]}`}
                        className="w-full"
                    >
                        <TabsList className="flex w-full flex-wrap">
                            {selectedGroupIds.map((groupId) => (
                                <TabsTrigger
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="flex items-center gap-2"
                                >
                                    {getGroupLabel(groupId)}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {selectedGroupIds.map((groupId) => {
                            const members = getGroupMembers(groupId);
                            const groupLabel = getGroupLabel(groupId);

                            return (
                                <TabsContent
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="mt-4 space-y-4"
                                >
                                    <div className="">
                                        <h4 className="mb-4 font-semibold">
                                            {groupLabel} Members
                                        </h4>
                                        {members.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border text-base">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                S/N
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Name (as per
                                                                passport)
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Relationship
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Passport No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Bed Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                DOB
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Age
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Meal
                                                            </th>
                                                            {!isView && (
                                                                <th className="p-2 text-left whitespace-nowrap">
                                                                    Actions
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {members.map(
                                                            (
                                                                member,
                                                                memberIdx,
                                                            ) => {
                                                                return (
                                                                    <tr
                                                                        key={
                                                                            memberIdx
                                                                        }
                                                                        className="border-b"
                                                                    >
                                                                        <td className="p-2">
                                                                            {
                                                                                memberIdx + 1
                                                                            }
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <p></p>
                                                                                <Input
                                                                                    value={
                                                                                        member?.name_as_per_passport ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'name_as_per_passport',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[180px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.name_as_per_passport`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.relationship ??
                                                                                        '-'
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'relationship',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.relationship`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.passport_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'passport_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[120px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.passport_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.room_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'room_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[80px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.room_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.room_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'room_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {ROOM_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            r,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    r
                                                                                                }
                                                                                                value={r.toLowerCase()}
                                                                                            >
                                                                                                {
                                                                                                    r
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.bed_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'bed_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {BED_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            b,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    b
                                                                                                }
                                                                                                value={
                                                                                                    b
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    b
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_birth
                                                                                        ? new Date(
                                                                                              member.date_of_birth,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_birth',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    type="number"
                                                                                    value={String(
                                                                                        member.age,
                                                                                    )}
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'age',
                                                                                            Number(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ) ||
                                                                                                0,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[60px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.age`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member.meal ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'meal',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {MEAL_OPTIONS.map(
                                                                                        (
                                                                                            m,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    m
                                                                                                }
                                                                                                value={
                                                                                                    m
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    m
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        {!isView && (
                                                                            <td className="p-2">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        removeTraveler(
                                                                                            memberIdx,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Minus className="h-4 w-4 text-destructive" />
                                                                                </Button>
                                                                            </td>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="py-4 text-center text-base text-muted-foreground">
                                                No members in this group.
                                            </p>
                                        )}
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

interface RoomListMadinahCardProps {
    isView: boolean;
    customerGroups: CustomerGroupData[];
    data: ManifestFormData;
    updateTraveler: (
        groupId: number,
        index: number,
        field: keyof TravelerSchema,
        value: string | number | boolean,
    ) => void;
    removeTraveler: (index: number) => void;
    renderError: (path: string) => React.ReactNode;
    selectedGroupIds: number[];
}

function RoomListMadinahCard({
    isView,
    customerGroups,
    data,
    updateTraveler,
    removeTraveler,
    renderError,
    selectedGroupIds,
}: RoomListMadinahCardProps) {
    const getGroupLabel = (groupId: number): string => {
        const group = customerGroups.find((g) => g.id === groupId);
        if (!group) return `Group ${groupId}`;
        const leader = group.members?.find((m) => m.is_leader);
        const leaderName = leader?.name || 'Unknown';
        return `Group - ${leaderName}`;
    };

    const getGroupMembers = (groupId: number): CustomerMemberData[] => {
        return data?.roomListMadinah?.[groupId] || [];
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Room List - Madinah</CardTitle>
            </CardHeader>
            <CardContent>
                {selectedGroupIds.length === 0 ? (
                    <p className="py-4 text-center text-base text-muted-foreground">
                        No groups added. Select a group from the Travelers tab.
                    </p>
                ) : (
                    <Tabs
                        defaultValue={`group-${selectedGroupIds[0]}`}
                        className="w-full"
                    >
                        <TabsList className="flex w-full flex-wrap">
                            {selectedGroupIds.map((groupId) => (
                                <TabsTrigger
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="flex items-center gap-2"
                                >
                                    {getGroupLabel(groupId)}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {selectedGroupIds.map((groupId) => {
                            const members = getGroupMembers(groupId);
                            const groupLabel = getGroupLabel(groupId);

                            return (
                                <TabsContent
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="mt-4 space-y-4"
                                >
                                    <div className="">
                                        <h4 className="mb-4 font-semibold">
                                            {groupLabel} Members
                                        </h4>
                                        {members.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border text-base">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                S/N
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Name (as per
                                                                passport)
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Relationship
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Passport No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Bed Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                DOB
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Age
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Meal
                                                            </th>
                                                            {!isView && (
                                                                <th className="p-2 text-left whitespace-nowrap">
                                                                    Actions
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {members.map(
                                                            (
                                                                member,
                                                                memberIdx,
                                                            ) => {
                                                                return (
                                                                    <tr
                                                                        key={
                                                                            memberIdx
                                                                        }
                                                                        className="border-b"
                                                                    >
                                                                        <td className="p-2">
                                                                            {memberIdx +
                                                                                1}
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <p></p>
                                                                                <Input
                                                                                    value={
                                                                                        member?.name_as_per_passport ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'name_as_per_passport',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[180px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.name_as_per_passport`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.relationship ??
                                                                                        '-'
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'relationship',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.relationship`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.passport_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'passport_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[120px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.passport_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.room_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'room_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[80px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.room_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.room_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'room_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {ROOM_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            r,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    r
                                                                                                }
                                                                                                value={r.toLowerCase()}
                                                                                            >
                                                                                                {
                                                                                                    r
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.bed_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'bed_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {BED_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            b,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    b
                                                                                                }
                                                                                                value={
                                                                                                    b
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    b
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_birth
                                                                                        ? new Date(
                                                                                              member.date_of_birth,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_birth',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    type="number"
                                                                                    value={String(
                                                                                        member.age,
                                                                                    )}
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'age',
                                                                                            Number(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ) ||
                                                                                                0,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[60px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.age`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member.meal ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'meal',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {MEAL_OPTIONS.map(
                                                                                        (
                                                                                            m,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    m
                                                                                                }
                                                                                                value={
                                                                                                    m
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    m
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        {!isView && (
                                                                            <td className="p-2">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        removeTraveler(
                                                                                            memberIdx,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Minus className="h-4 w-4 text-destructive" />
                                                                                </Button>
                                                                            </td>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="py-4 text-center text-base text-muted-foreground">
                                                No members in this group.
                                            </p>
                                        )}
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

interface RoomListOthersCardProps {
    isView: boolean;
    customerGroups: CustomerGroupData[];
    data: ManifestFormData;
    updateTraveler: (
        groupId: number,
        index: number,
        field: keyof TravelerSchema,
        value: string | number | boolean,
    ) => void;
    removeTraveler: (index: number) => void;
    renderError: (path: string) => React.ReactNode;
    selectedGroupIds: number[];
}

function RoomListOthersCard({
    isView,
    customerGroups,
    data,
    updateTraveler,
    removeTraveler,
    renderError,
    selectedGroupIds,
}: RoomListOthersCardProps) {
    const getGroupLabel = (groupId: number): string => {
        const group = customerGroups.find((g) => g.id === groupId);
        if (!group) return `Group ${groupId}`;
        const leader = group.members?.find((m) => m.is_leader);
        const leaderName = leader?.name || 'Unknown';
        return `Group - ${leaderName}`;
    };

    const getGroupMembers = (groupId: number): CustomerMemberData[] => {
        return data?.roomListOthers?.[groupId] || [];
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Room List - Others</CardTitle>
            </CardHeader>
            <CardContent>
                {selectedGroupIds.length === 0 ? (
                    <p className="py-4 text-center text-base text-muted-foreground">
                        No groups added. Select a group from the Travelers tab.
                    </p>
                ) : (
                    <Tabs
                        defaultValue={`group-${selectedGroupIds[0]}`}
                        className="w-full"
                    >
                        <TabsList className="flex w-full flex-wrap">
                            {selectedGroupIds.map((groupId) => (
                                <TabsTrigger
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="flex items-center gap-2"
                                >
                                    {getGroupLabel(groupId)}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {selectedGroupIds.map((groupId) => {
                            const members = getGroupMembers(groupId);
                            const groupLabel = getGroupLabel(groupId);

                            return (
                                <TabsContent
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="mt-4 space-y-4"
                                >
                                    <div className="">
                                        <h4 className="mb-4 font-semibold">
                                            {groupLabel} Members
                                        </h4>
                                        {members.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border text-base">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                S/N
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Name (as per
                                                                passport)
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Relationship
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Passport No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Room Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Bed Type
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                DOB
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Age
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Meal
                                                            </th>
                                                            {!isView && (
                                                                <th className="p-2 text-left whitespace-nowrap">
                                                                    Actions
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {members.map(
                                                            (
                                                                member,
                                                                memberIdx,
                                                            ) => {
                                                                return (
                                                                    <tr
                                                                        key={
                                                                            memberIdx
                                                                        }
                                                                        className="border-b"
                                                                    >
                                                                        <td className="p-2">
                                                                            {memberIdx +
                                                                                1}
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <p></p>
                                                                                <Input
                                                                                    value={
                                                                                        member?.name_as_per_passport ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'name_as_per_passport',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[180px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.name_as_per_passport`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.relationship ??
                                                                                        '-'
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'relationship',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.relationship`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.passport_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'passport_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[120px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.passport_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member?.room_no ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'room_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[80px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.room_no`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.room_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'room_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {ROOM_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            r,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    r
                                                                                                }
                                                                                                value={r.toLowerCase()}
                                                                                            >
                                                                                                {
                                                                                                    r
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member?.bed_type ??
                                                                                    ''
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'bed_type',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {BED_TYPE_OPTIONS.map(
                                                                                        (
                                                                                            b,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    b
                                                                                                }
                                                                                                value={
                                                                                                    b
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    b
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_birth
                                                                                        ? new Date(
                                                                                              member.date_of_birth,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_birth',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    type="number"
                                                                                    value={String(
                                                                                        member.age,
                                                                                    )}
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'age',
                                                                                            Number(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ) ||
                                                                                                0,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    className="min-w-[60px]"
                                                                                />
                                                                                {renderError(
                                                                                    `travelers.${memberIdx}.age`,
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member.meal ??
                                                                                    ""
                                                                                }
                                                                                onValueChange={(
                                                                                    v,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'meal',
                                                                                        v,
                                                                                    )
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {MEAL_OPTIONS.map(
                                                                                        (
                                                                                            m,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    m
                                                                                                }
                                                                                                value={
                                                                                                    m
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    m
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        {!isView && (
                                                                            <td className="p-2">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        removeTraveler(
                                                                                            memberIdx,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Minus className="h-4 w-4 text-destructive" />
                                                                                </Button>
                                                                            </td>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="py-4 text-center text-base text-muted-foreground">
                                                No members in this group.
                                            </p>
                                        )}
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

interface AirlinesNameListCardProps {
    isView: boolean;
    customerGroups: CustomerGroupData[];
    data: ManifestFormData;
    updateTraveler: (
        groupId: number,
        index: number,
        field: keyof TravelerSchema,
        value: string | number | boolean,
    ) => void;
    removeTraveler: (index: number) => void;
    renderError: (path: string) => React.ReactNode;
    selectedGroupIds: number[];
}

function AirlinesNameListCard({
    isView,
    customerGroups,
    data,
    updateTraveler,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    removeTraveler,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    renderError,
    selectedGroupIds,
}: AirlinesNameListCardProps) {
    const getGroupLabel = (groupId: number): string => {
        const group = customerGroups.find((g) => g.id === groupId);
        if (!group) return `Group ${groupId}`;
        const leader = group.members?.find((m) => m.is_leader);
        const leaderName = leader?.name || 'Unknown';
        return `Group - ${leaderName}`;
    };

    const getGroupMembers = (groupId: number): CustomerMemberData[] => {
        return data.airlineList?.[groupId] || [];
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Airlines Name List</CardTitle>
            </CardHeader>
            <CardContent>
                {selectedGroupIds.length === 0 ? (
                    <p className="py-4 text-center text-base text-muted-foreground">
                        No groups added. Select a group from the Travelers tab.
                    </p>
                ) : (
                    <Tabs
                        defaultValue={`group-${selectedGroupIds[0]}`}
                        className="w-full"
                    >
                        <TabsList className="flex w-full flex-wrap">
                            {selectedGroupIds.map((groupId) => (
                                <TabsTrigger
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="flex items-center gap-2"
                                >
                                    {getGroupLabel(groupId)}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {selectedGroupIds.map((groupId) => {
                            const members = getGroupMembers(groupId);
                            const groupLabel = getGroupLabel(groupId);

                            return (
                                <TabsContent
                                    key={groupId}
                                    value={`group-${groupId}`}
                                    className="mt-4 space-y-4"
                                >
                                    <div className="">
                                        <h4 className="mb-4 font-semibold">
                                            {groupLabel} Passenger List
                                        </h4>
                                        {members.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border text-sm">
                                                    <thead>
                                                        <tr className="border-b bg-muted/50">
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                S/N
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Name as per
                                                                Passport
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Nationality
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                PPT No
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Gender
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.B
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.I
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                D.O.E
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Issue Place
                                                            </th>
                                                            <th className="p-2 text-left whitespace-nowrap">
                                                                Remarks
                                                            </th>
                                                            {!isView && (
                                                                <th className="p-2 text-left whitespace-nowrap">
                                                                    Actions
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {members.map(
                                                            (
                                                                member,
                                                                memberIdx,
                                                            ) => {
                                                                return (
                                                                    <tr
                                                                        key={`${groupId}-${memberIdx}`}
                                                                        className="border-b"
                                                                    >
                                                                        <td className="p-2">
                                                                            {memberIdx +1}
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member.name_as_per_passport ??
                                                                                        ''
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'name_as_per_passport',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="min-w-[150px]"
                                                                                />
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member.nationality ??
                                                                                        ''
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'nationality',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member.passport_no ??
                                                                                        ''
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'passport_no',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Select
                                                                                value={
                                                                                    member.gender ??
                                                                                    ''
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                onValueChange={(
                                                                                    g,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'gender',
                                                                                        g,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <SelectTrigger className="min-w-[100px]">
                                                                                    <SelectValue placeholder="-" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {GENDER_OPTIONS.map(
                                                                                        (
                                                                                            g,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    g
                                                                                                }
                                                                                                value={
                                                                                                    g
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    g
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_birth
                                                                                        ? new Date(
                                                                                              member.date_of_birth,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_birth',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_issue
                                                                                        ? new Date(
                                                                                              member.date_of_issue,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_issue',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <Input
                                                                                type="date"
                                                                                value={
                                                                                    member.date_of_expiry
                                                                                        ? new Date(
                                                                                              member.date_of_expiry,
                                                                                          )
                                                                                              .toISOString()
                                                                                              .split(
                                                                                                  'T',
                                                                                              )[0]
                                                                                        : ''
                                                                                }
                                                                                disabled={
                                                                                    isView
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateTraveler(
                                                                                        groupId,
                                                                                        memberIdx,
                                                                                        'date_of_expiry',
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                                className="min-w-[130px]"
                                                                            />
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member.issue_place ??
                                                                                        ''
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'issue_place',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="min-w-[120px]"
                                                                                />
                                                                            </div>
                                                                        </td>
                                                                        <td className="p-2">
                                                                            <div className="relative">
                                                                                <Input
                                                                                    value={
                                                                                        member.remarks ??
                                                                                        ''
                                                                                    }
                                                                                    disabled={
                                                                                        isView
                                                                                    }
                                                                                    onChange={(
                                                                                        e,
                                                                                    ) =>
                                                                                        updateTraveler(
                                                                                            groupId,
                                                                                            memberIdx,
                                                                                            'remarks',
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                    className="min-w-[100px]"
                                                                                />
                                                                            </div>
                                                                        </td>
                                                                        {!isView && (
                                                                            <td className="p-2">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                >
                                                                                    <Minus className="h-4 w-4 text-destructive" />
                                                                                </Button>
                                                                            </td>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="py-4 text-center text-base text-muted-foreground">
                                                No members in this group.
                                            </p>
                                        )}
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

export default function ManifestForm({
    mode,
    initialData,
    dataPackage = [],
    customerGroups = [],
    onCancel,
}: ManifestFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const { data: defaultsData, selectedGroups: defaultSelectedGroups } =
        getDefaultValues(initialData);

    const form = useForm<ManifestFormData>(defaultsData);
    const { data, setData, processing, reset, errors, setError, clearErrors } =
        form;

    // State untuk tracking selected customer groups - initialize from defaults
    const [selectedCustomerGroupIds, setSelectedCustomerGroupIds] = useState<
        number[]
    >(defaultSelectedGroups);

    // Helper function untuk menambah group dan populate travelers
    const addCustomerGroup = useCallback(
        (groupId: number) => {
            if (selectedCustomerGroupIds.includes(groupId)) return;

            // Tambahkan groupId ke selected groups
            setSelectedCustomerGroupIds([...selectedCustomerGroupIds, groupId]);

            // Find group dan ambil members
            const group = customerGroups.find((g) => g.id === groupId);

            const travelers = data.travelers || [];

            if (group && group.members) {
                travelers[groupId] = group.members.map((member, idx) => ({
                    id: member.id,
                    customer_id: member.customer_id,
                    customer_group_id: groupId,
                    package_id: data.package_id,
                    name_as_per_passport: member.name,
                    date_of_sign_up: new Date().toISOString().split('T')[0],
                    is_first_time_umrah: false,
                    ppt_no: member.passport_number || '',
                    gender: member.gender || '',
                    date_of_birth: member.date_of_birth || '',
                    age: member.age || 0,
                    contact_no: member.contact || '',
                    date_of_issue: member.passport_issue_date || '',
                    date_of_expiry: member.passport_expiry_date || '',
                    issue_place: member.passport_place_of_issue || '',
                    birth_place: 'indonesia',
                    package_price: 0,
                    discount: 0,
                    date_of_deposit_payment: '',
                    deposit_payment: 0,
                    date_of_second_payment: '',
                    second_payment: 0,
                    balance_due: 0,
                    is_fully_paid: false,
                    receipt_no: '',
                    remarks: '',
                    sn: (data.travelers?.length ?? 0) + idx + 1,
                }));

                setData('travelers', travelers);

                // Initialize roomListMakkah
                const roomListMakkah = data.roomListMakkah || {};
                roomListMakkah[groupId] = group.members.map((member) => ({
                    id: member.id,
                    customer_id: member.customer_id,
                    customer_group_id: groupId,
                    name_as_per_passport: member.name,
                    relationship: member.is_leader ? 'Self' : 'Self',
                    passport_no: member.passport_number || '',
                    room_no: '',
                    location: '',
                    room_number: '',
                    room_type: '',
                    bed_type: '',
                    date_of_birth: member.date_of_birth || '',
                    age: member.age || 0,
                    no_of_beds_checked: 0,
                    meal: '',
                    remarks: '',
                }));
                setData('roomListMakkah', roomListMakkah);

                // Initialize roomListMadinah
                const roomListMadinah = data.roomListMadinah || {};
                roomListMadinah[groupId] = group.members.map((member) => ({
                    id: member.id,
                    customer_id: member.customer_id,
                    customer_group_id: groupId,
                    name_as_per_passport: member.name,
                    relationship: member.is_leader ? 'Self' : 'Self',
                    passport_no: member.passport_number || '',
                    room_no: '',
                    location: '',
                    room_number: '',
                    room_type: '',
                    bed_type: '',
                    date_of_birth: member.date_of_birth || '',
                    age: member.age || 0,
                    no_of_beds_checked: 0,
                    meal: '',
                    remarks: '',
                }));
                setData('roomListMadinah', roomListMadinah);

                // Initialize roomListOthers
                const roomListOthers = data.roomListOthers || {};
                roomListOthers[groupId] = group.members.map((member) => ({
                    id: member.id,
                    customer_id: member.customer_id,
                    customer_group_id: groupId,
                    name_as_per_passport: member.name,
                    relationship: member.is_leader ? 'Self' : 'Self',
                    passport_no: member.passport_number || '',
                    room_no: '',
                    location: '',
                    room_number: '',
                    room_type: '',
                    bed_type: '',
                    date_of_birth: member.date_of_birth || '',
                    age: member.age || 0,
                    no_of_beds_checked: 0,
                    meal: '',
                    remarks: '',
                }));
                setData('roomListOthers', roomListOthers);

                // Initialize airlineList
                const airlineList = data.airlineList || {};
                airlineList[groupId] = group.members.map((member, idx) => ({
                    id: member.id,
                    customer_id: member.customer_id,
                    customer_group_id: groupId,
                    sn: (data.travelers?.length ?? 0) + idx + 1,
                    name_as_per_passport: member.name,
                    nationality: '',
                    passport_no: member.passport_number || '',
                    gender: '',
                    date_of_birth: member.date_of_birth || '',
                    date_of_issue: member.passport_issue_date || '',
                    date_of_expiry: member.passport_expiry_date || '',
                    issue_place: member.passport_place_of_issue || '',
                    remarks: '',
                }));
                setData('airlineList', airlineList);
            }
        },
        [
            selectedCustomerGroupIds,
            customerGroups,
            data.travelers,
            data.roomListMakkah,
            data.roomListMadinah,
            data.roomListOthers,
            data.airlineList,
            data.package_id,
            setData,
        ],
    );

    // Helper function untuk remove group dan hapus travelers
    const removeCustomerGroup = useCallback(
        (groupId: number) => {
            // Remove dari selected groups
            setSelectedCustomerGroupIds(
                selectedCustomerGroupIds.filter((id) => id !== groupId),
            );

            // Find group
            const group = customerGroups.find((g) => g.id === groupId);
            if (group && group.members) {
                // Get member names yang akan dihapus
                const memberNames = group.members.map((m) => m.name);

                // Filter travelers - hapus yang sesuai dengan group members
                const updatedTravelers = (data.travelers ?? []).filter(
                    (traveler: TravelerSchema) =>
                        !memberNames.includes(
                            traveler.name_as_per_passport ?? '',
                        ),
                );

                // Re-number sn
                const renumberedTravelers = updatedTravelers.map(
                    (traveler: TravelerSchema, idx: number) => ({
                        ...traveler,
                        sn: idx + 1,
                    }),
                );

                setData('travelers', renumberedTravelers);

                // Remove roomListMakkah for this group
                const updatedRoomListMakkah = { ...data.roomListMakkah };
                delete updatedRoomListMakkah[groupId];
                setData('roomListMakkah', updatedRoomListMakkah);

                // Remove roomListMadinah for this group
                const updatedRoomListMadinah = { ...data.roomListMadinah };
                delete updatedRoomListMadinah[groupId];
                setData('roomListMadinah', updatedRoomListMadinah);

                // Remove roomListOthers for this group
                const updatedRoomListOthers = { ...data.roomListOthers };
                delete updatedRoomListOthers[groupId];
                setData('roomListOthers', updatedRoomListOthers);

                // Remove airlineList for this group
                const updatedAirlineList = { ...data.airlineList };
                delete updatedAirlineList[groupId];
                setData('airlineList', updatedAirlineList);
            }
        },
        [
            selectedCustomerGroupIds,
            customerGroups,
            data.travelers,
            data.roomListMakkah,
            data.roomListMadinah,
            data.roomListOthers,
            data.airlineList,
            setData,
        ],
    );

    const updateTraveler = useCallback(
        (
            groupId: number,
            index: number,
            field: keyof TravelerSchema,
            value: string | number | boolean,
        ) => {
            const travelers = data.travelers || [];
            travelers[groupId][index] = {
                ...travelers[groupId][index],
                [field]: value,
            };
            setData('travelers', travelers);
        },
        [data.travelers, setData],
    );

    const updateRoomListMakkah = useCallback(
        (
            groupId: number,
            index: number,
            field: keyof TravelerSchema,
            value: string | number | boolean,
        ) => {
            const roomListMakkah = data.roomListMakkah || [];
            roomListMakkah[groupId][index] = {
                ...roomListMakkah[groupId][index],
                [field]: value,
            };
            setData('roomListMakkah', roomListMakkah);
        },
        [data.roomListMakkah, setData],
    );

    const updateRoomListMadinah = useCallback(
        (
            groupId: number,
            index: number,
            field: keyof TravelerSchema,
            value: string | number | boolean,
        ) => {
            const roomListMadinah = data.roomListMadinah || [];
            roomListMadinah[groupId][index] = {
                ...roomListMadinah[groupId][index],
                [field]: value,
            };
            setData('roomListMadinah', roomListMadinah);
        },
        [data.roomListMadinah, setData],
    );

    const updateRoomListOthers = useCallback(
        (
            groupId: number,
            index: number,
            field: keyof TravelerSchema,
            value: string | number | boolean,
        ) => {
            const roomListOthers = data.roomListOthers || [];
            roomListOthers[groupId][index] = {
                ...roomListOthers[groupId][index],
                [field]: value,
            };
            setData('roomListOthers', roomListOthers);
        },
        [data.roomListOthers, setData],
    );

    const updateAirlineList = useCallback(
        (
            groupId: number,
            index: number,
            field: keyof TravelerSchema,
            value: string | number | boolean,
        ) => {
            const airlineList = data.airlineList || [];
            airlineList[groupId][index] = {
                ...airlineList[groupId][index],
                [field]: value,
            };
            setData('airlineList', airlineList);
        },
        [data.airlineList, setData],
    );

    const removeTraveler = useCallback(
        (index: number) => {
            const updatedTravelers = (data.travelers ?? {}) as Record<number, TravelerSchema[]>;
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const filtered = Object.fromEntries(
                Object.entries(updatedTravelers).map(([key, travelers]) => [
                    key,
                    travelers.filter((_, i: number) => i !== index),
                ]),
            );
            setData('travelers', updatedTravelers);
        },
        [data.travelers, setData],
    );

    const result = manifestValidationSchema.safeParse(data);
    
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const _validateClientSide = (): boolean => {
        clearErrors();
        let valid = true;
        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof ManifestSchema;
                setError(key, issue.message);
            });
            valid = false;
        }

        return valid;
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        const formattedData = data;

        if (isCreate) {
            router.post(store().url, formattedData, {
                onError: (errors: Record<string, string>) => setError(errors),
            });
        } else if (isEdit && data.id) {
            router.put(update(data.id).url, formattedData, {
                onError: (errors: Record<string, string>) => setError(errors),
            });
        }
    };

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];
        if (!message) return null;
        return <p className="mt-1 text-sm text-red-500">{message}</p>;
    };

    return (
        <form className="space-y-6">
            {/* Error Alert */}
            {Object.keys(errors).length > 0 && !isView && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the errors below and try again
                    </AlertDescription>
                </Alert>
            )}

            <ManifestInfoCard
                isView={isView}
                data={data}
                setData={setData}
                dataPackage={dataPackage}
                renderError={renderError}
            />

            <HotelDetailsCard
                isView={isView}
                data={data}
                setData={setData}
                renderError={renderError}
            />

            <MealsNotesCard
                isView={isView}
                data={data}
                setData={setData}
                renderError={renderError}
            />

            {/* <p>{JSON.stringify(data)}</p> */}

            <Tabs defaultValue="travelers" className="w-full">
                <TabsList className="flex w-full flex-wrap">
                    <TabsTrigger value="travelers">Travelers</TabsTrigger>
                    <TabsTrigger value="room-list-makkah">
                        Room List Makkah
                    </TabsTrigger>
                    <TabsTrigger value="room-list-madinah">
                        Room List Madinah
                    </TabsTrigger>
                    <TabsTrigger value="room-list-others">
                        Room List Others
                    </TabsTrigger>
                    <TabsTrigger value="airlines-namelist">
                        Airlines NameList
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="travelers">
                    <TravelersByGroupCard
                        isView={isView}
                        customerGroups={customerGroups}
                        data={data}
                        updateTraveler={updateTraveler}
                        removeTraveler={removeTraveler}
                        renderError={renderError}
                        selectedGroupIds={selectedCustomerGroupIds}
                        onAddGroup={addCustomerGroup}
                        onRemoveGroup={removeCustomerGroup}
                    />
                </TabsContent>
                <TabsContent value="room-list-makkah" className="mt-4">
                    <RoomListMakkahCard
                        isView={isView}
                        customerGroups={customerGroups}
                        data={data}
                        updateTraveler={updateRoomListMakkah}
                        removeTraveler={removeTraveler}
                        renderError={renderError}
                        selectedGroupIds={selectedCustomerGroupIds}
                    />
                </TabsContent>
                <TabsContent value="room-list-madinah" className="mt-4">
                    <RoomListMadinahCard
                        isView={isView}
                        customerGroups={customerGroups}
                        data={data}
                        updateTraveler={updateRoomListMadinah}
                        removeTraveler={removeTraveler}
                        renderError={renderError}
                        selectedGroupIds={selectedCustomerGroupIds}
                    />
                </TabsContent>
                <TabsContent value="room-list-others" className="mt-4">
                    <RoomListOthersCard
                        isView={isView}
                        customerGroups={customerGroups}
                        data={data}
                        updateTraveler={updateRoomListOthers}
                        removeTraveler={removeTraveler}
                        renderError={renderError}
                        selectedGroupIds={selectedCustomerGroupIds}
                    />
                </TabsContent>
                <TabsContent value="airlines-namelist" className="mt-4">
                    <AirlinesNameListCard
                        isView={isView}
                        customerGroups={customerGroups}
                        data={data}
                        updateTraveler={updateAirlineList}
                        removeTraveler={removeTraveler}
                        renderError={renderError}
                        selectedGroupIds={selectedCustomerGroupIds}
                    />
                </TabsContent>
            </Tabs>

            {/* Action Buttons */}
            <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Back
                </Button>
                {!isView && (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => reset()}
                        >
                            <RotateCcw className="mr-1 h-4 w-4" />
                            Reset
                        </Button>
                        <Button onClick={handleSubmit} disabled={processing}>
                            {isCreate ? 'Create Manifest' : 'Update Manifest'}
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
