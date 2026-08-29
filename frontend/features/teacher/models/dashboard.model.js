import { normalizeBook } from '../../student/models/book.model.js';
import { normalizeLoan } from '../../student/models/loan.model.js';
import { normalizeTeacherUser } from './user.model.js';

const number = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

export function normalizeTeacherDashboard(value = {}) {
  const stats = value.stats || {};
  return {
    user: normalizeTeacherUser(value.user),
    stats: {
      active: number(stats.active),
      overdue: number(stats.overdue),
      fines: number(stats.fines),
      on_time_rate: number(stats.on_time_rate, 100),
    },
    max_books: number(value.max_books, 5),
    due_soon: Array.isArray(value.due_soon) ? value.due_soon.map((item) => ({ ...item })) : [],
    recommended: Array.isArray(value.recommended) ? value.recommended.map(normalizeBook) : [],
    favorite_category: value.favorite_category == null ? '' : String(value.favorite_category),
    achievements: Array.isArray(value.achievements) ? value.achievements.map((item) => Array.isArray(item) ? [...item] : item) : [],
    current_loans: Array.isArray(value.current_loans) ? value.current_loans.map(normalizeLoan) : [],
  };
}
