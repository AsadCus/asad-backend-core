import { DateTime } from 'luxon';

export function formatUserTime(dateString: string) {
    if (!dateString) return 'Never';

    const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone;

    let dt = DateTime.fromSQL(dateString, { zone: 'utc' });

    if (!dt.isValid) {
        dt = DateTime.fromISO(dateString, { zone: 'utc' });
    }

    if (!dt.isValid) {
        const asDate = new Date(dateString);
        if (!isNaN(asDate.getTime())) {
            dt = DateTime.fromJSDate(asDate, { zone: 'utc' });
        }
    }

    if (!dt.isValid) return 'Invalid date';

    dt = dt.setZone(userTz);

    const now = DateTime.now().setZone(userTz);
    const diffMinutes = now.diff(dt, 'minutes').minutes;

    let display = '';

    if (diffMinutes < 1) {
        display = 'Just now';
    } else if (diffMinutes < 60) {
        display = `${Math.floor(diffMinutes)} minute${Math.floor(diffMinutes) > 1 ? 's' : ''} ago`;
    } else if (diffMinutes < 1440) {
        const hours = Math.floor(diffMinutes / 60);
        display = `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        const days = Math.floor(diffMinutes / 1440);
        display = `${days} day${days > 1 ? 's' : ''} ago`;
    }

    return `${display}`;
    // return `${display} (${dt.toFormat('yyyy-LL-dd HH:mm:ss')})`;
}
