const text = (value) => value == null ? '' : String(value);

const fields = ['firstname', 'middlename', 'lastname', 'email', 'contact_no', 'course', 'year_level', 'department', 'position', 'photo'];

export function normalizeStudentProfileChange(value = {}) {
  const profile = value.profile || {};
  const pending = value.pending_request || null;
  return {
    profile: Object.fromEntries(fields.map((field) => [field, text(profile[field])])),
    requestable_fields: Array.isArray(value.requestable_fields) ? value.requestable_fields.map(text) : fields,
    pending_request: pending ? {
      id: Number(pending.id || 0),
      status: text(pending.status),
      original_values: pending.original_values || {},
      requested_values: pending.requested_values || {},
      original_photo: text(pending.original_photo),
      requested_photo: text(pending.requested_photo),
      requested_at: text(pending.requested_at),
      review_note: text(pending.review_note),
    } : null,
  };
}
