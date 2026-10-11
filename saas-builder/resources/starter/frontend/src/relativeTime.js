// "3 days ago" for Date Created (law L-UI-001). CRM dates are stored in UTC.
const units = [
  ['year', 31536000],
  ['month', 2592000],
  ['week', 604800],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
];

const format = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function parseUtc(value) {
  if (!value) return null;
  const date = new Date(String(value).replace(' ', 'T') + (String(value).endsWith('Z') ? '' : 'Z'));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function relativeTime(value, now = Date.now()) {
  const date = parseUtc(value);
  if (!date) return '';
  const seconds = Math.round((date.getTime() - now) / 1000);
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit);
  }
  return 'just now';
}
