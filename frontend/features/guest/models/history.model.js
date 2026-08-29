const text = (value) => value == null ? '' : String(value);

function normalizeRow(value = {}) {
  return {
    id: text(value.id),
    title: text(value.title),
    author: text(value.author),
    borrow_date: text(value.borrow_date),
    due_date: text(value.due_date),
    return_date: text(value.return_date),
    request_status: text(value.request_status) || 'Pending',
    status_label: text(value.status_label),
    review_notes: text(value.review_notes),
    remaining_label: text(value.remaining_label),
  };
}

export function normalizeGuestHistory(value = []) {
  return Array.isArray(value) ? value.map(normalizeRow) : normalizeRow(value);
}
