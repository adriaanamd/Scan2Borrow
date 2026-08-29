const text = (value) => value == null ? '' : String(value);

export function normalizeLoan(value = {}) {
  return {
    title: text(value.title),
    author: text(value.author),
    borrow_date: text(value.borrow_date),
    due_date: text(value.due_date),
    status: text(value.status),
    transaction_code: text(value.transaction_code),
  };
}
