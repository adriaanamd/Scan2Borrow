import { normalizeGuestHistory } from './history.model.js';
import { normalizeGuestVisitor } from './visitor.model.js';

const number = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

export function normalizeGuestDashboard(value = {}) {
  const summary = value.summary || {};
  return {
    visitor: normalizeGuestVisitor(value.visitor),
    summary: {
      active: number(summary.active),
      returned: number(summary.returned),
      overdue: number(summary.overdue),
      total: number(summary.total),
    },
    days_remaining: number(value.days_remaining),
    favorite_category: value.favorite_category == null ? '' : String(value.favorite_category),
    recent_book: value.recent_book == null ? '' : String(value.recent_book),
    visit_history: Array.isArray(value.visit_history) ? value.visit_history.map((item) => ({ ...item })) : [],
    security_log: Array.isArray(value.security_log) ? value.security_log.map((item) => ({ ...item })) : [],
    history: Array.isArray(value.history) ? normalizeGuestHistory(value.history) : [],
  };
}
