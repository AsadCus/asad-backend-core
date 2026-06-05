import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/customer-history';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    History,
    Mail,
    Phone,
    Search,
    User,
} from 'lucide-react';
import { useState } from 'react';
import CustomerHistoryDialog from './components/customer-history-dialog';
import type { CustomerSearchResult } from './types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Customer History',
        href: index().url,
    },
];

interface CustomerHistoryPageProps {
    customers: CustomerSearchResult[];
    search: string;
}

export default function CustomerHistoryPage({
    customers,
    search: initialSearch,
}: CustomerHistoryPageProps) {
    const [searchValue, setSearchValue] = useState(initialSearch);
    const [historyCustomerId, setHistoryCustomerId] = useState<
        number | undefined
    >();
    const [historyCustomerName, setHistoryCustomerName] = useState<string>('');
    const [historyCustomerEmail, setHistoryCustomerEmail] =
        useState<string>('');
    const [historyCustomerContact, setHistoryCustomerContact] =
        useState<string>('');
    const [historyOpen, setHistoryOpen] = useState(false);

    const handleSearch = () => {
        router.get(
            index().url,
            { search: searchValue },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const openHistory = (customer: CustomerSearchResult) => {
        setHistoryCustomerId(customer.customer_id);
        setHistoryCustomerName(customer.name);
        setHistoryCustomerEmail(customer.email);
        setHistoryCustomerContact(customer.contact);
        setHistoryOpen(true);
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Customer History" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex gap-2">
                            <h2 className="text-lg font-semibold">
                                Customer History
                            </h2>
                        </div>
                    </div>

                    {/* Search Section */}
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-4 py-4 not-dark:bg-white dark:border-sidebar-border">
                        <div className="mb-4">
                            <p className="mb-3 text-sm text-muted-foreground">
                                Search for a customer to view their travel
                                history.
                                You can search by name, email, contact
                                number, NRIC, or customer number.
                            </p>
                            <div className="flex max-w-xl gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="customer-history-search"
                                        placeholder="Search..."
                                        value={searchValue}
                                        onChange={(e) =>
                                            setSearchValue(e.target.value)
                                        }
                                        onKeyDown={handleKeyDown}
                                        className="pl-9"
                                    />
                                </div>
                                <Button
                                    onClick={handleSearch}
                                    disabled={!searchValue.trim()}
                                >
                                    Apply
                                </Button>
                            </div>
                        </div>

                        {/* Results */}
                        {initialSearch && customers.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                <Search className="mb-3 h-10 w-10 opacity-40" />
                                <p className="text-base font-medium">
                                    No customers found
                                </p>
                                <p className="mt-1 text-sm">
                                    Try a different search term.
                                </p>
                            </div>
                        )}

                        {!initialSearch && customers.length === 0 && (
                            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                <History className="mb-3 h-10 w-10 opacity-40" />
                                <p className="text-base font-medium">
                                    Search for a customer
                                </p>
                                <p className="mt-1 text-sm">
                                    Enter a search term above to find customers
                                    and view their travel history.
                                </p>
                            </div>
                        )}

                        {customers.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">
                                    {customers.length} customer
                                    {customers.length !== 1 ? 's' : ''} found
                                </p>
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {customers.map((customer) => (
                                        <button
                                            key={customer.id}
                                            type="button"
                                            onClick={() =>
                                                openHistory(customer)
                                            }
                                            className="group flex cursor-pointer flex-col gap-1.5 rounded-lg border border-gray-100 bg-gray-50/50 p-4 text-left transition-all hover:border-primary/30 hover:bg-primary/5 dark:border-gray-800 dark:bg-gray-900/50 dark:hover:border-primary/30 dark:hover:bg-primary/5"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="flex items-center gap-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                    <User className="h-4 w-4" />
                                                    {customer.name}
                                                </span>
                                                {customer.customer_number && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {
                                                            customer.customer_number
                                                        }
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex flex-col gap-0.5 text-sm text-muted-foreground">
                                                {customer.email && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Mail className="h-3.5 w-3.5" />
                                                        {customer.email}
                                                    </span>
                                                )}
                                                {customer.contact &&
                                                    customer.contact !==
                                                        '-' && (
                                                        <span className="flex items-center gap-1.5">
                                                            <Phone className="h-3.5 w-3.5" />
                                                            {customer.contact}
                                                        </span>
                                                    )}
                                            </div>
                                            <span className="mt-1 text-xs text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                                Click to view travel history →
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </AppLayout>

            <CustomerHistoryDialog
                isOpen={historyOpen}
                onClose={() => setHistoryOpen(false)}
                customerId={historyCustomerId}
                customerName={historyCustomerName}
                customerEmail={historyCustomerEmail}
                customerContact={historyCustomerContact}
            />
        </>
    );
}
