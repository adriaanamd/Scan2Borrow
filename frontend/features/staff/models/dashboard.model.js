const number = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

export function normalizeStaffDashboard(value = {}) {
  const rawStats = value.stats || {};
  const stats = Object.fromEntries([
    'total_books', 'available_books', 'borrowed_books', 'borrowers',
    'active_loans', 'overdue_loans', 'pending_approvals',
  ].map((key) => [key, number(rawStats[key])]));
  return {
    stats,
    pending: Array.isArray(value.pending) ? value.pending.map((row) => ({ ...row })) : [],
    overview: {
      borrowing_activity: Array.isArray(value.overview?.borrowing_activity) ? value.overview.borrowing_activity.map((row) => ({ ...row })) : [],
      category_borrowing_activity: { ...(value.overview?.category_borrowing_activity || {}) },
      loan_status: { ...(value.overview?.loan_status || {}) },
      top_borrowers: Array.isArray(value.overview?.top_borrowers) ? value.overview.top_borrowers.map((row) => ({ ...row })) : [],
      category_breakdown: Array.isArray(value.overview?.category_breakdown) ? value.overview.category_breakdown.map((row) => ({ ...row })) : [],
      top_genres: Array.isArray(value.overview?.top_genres) ? value.overview.top_genres.map((row) => ({ ...row })) : [],
      recent_activity: Array.isArray(value.overview?.recent_activity) ? value.overview.recent_activity.map((row) => ({ ...row })) : [],
    },
    recent: Array.isArray(value.recent) ? value.recent.map((row) => ({ ...row })) : [],
  };
}
