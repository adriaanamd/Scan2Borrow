const STATUS_CLASSES = {
  Borrowed: 'primary',
  Overdue: 'danger',
  Pending: 'warning text-dark',
  Returned: 'success',
};

export function formatDate(value) {
  if (!value) return '';

  const date = new Date(value);
  return Number.isNaN(date.valueOf())
    ? String(value)
    : date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
      });
}

export function formatPeso(value) {
  return '\u20B1' + Number(value).toFixed(2);
}

export function statusClass(status) {
  return STATUS_CLASSES[status] || 'secondary';
}
