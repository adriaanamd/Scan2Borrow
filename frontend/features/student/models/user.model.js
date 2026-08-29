const text = (value) => value == null ? '' : String(value);

export function normalizeUser(value = {}) {
  return {
    name: text(value.name),
    barcode: text(value.barcode),
    role: text(value.role) || 'Student',
    course: text(value.course),
    year_level: text(value.year_level),
  };
}
