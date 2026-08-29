const text = (value) => value == null ? '' : String(value);

export function normalizeTeacherUser(value = {}) {
  return {
    name: text(value.name),
    barcode: text(value.barcode),
    role: text(value.role) || 'Teacher',
    department: text(value.department),
    position: text(value.position),
    contact_no: text(value.contact_no),
  };
}
