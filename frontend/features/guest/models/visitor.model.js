const text = (value) => value == null ? '' : String(value);

export function normalizeGuestVisitor(value = {}) {
  return {
    name: text(value.name),
    visitor_number: text(value.visitor_number),
    account_status: text(value.account_status) || 'Active',
    registration_expires_at: text(value.registration_expires_at),
    contact_no: text(value.contact_no),
    email: text(value.email),
    purpose: text(value.purpose),
    purpose_other: text(value.purpose_other),
    id_type: text(value.id_type),
    id_barcode: text(value.id_barcode),
    photo_data: text(value.photo_data),
  };
}
