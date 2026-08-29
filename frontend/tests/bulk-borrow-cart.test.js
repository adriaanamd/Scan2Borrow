import test from "node:test";
import assert from "node:assert/strict";
import { BulkBorrowCart } from "../app/core/models/bulk-borrow-cart.js";

test("repeated scans merge into one title line and retain barcodes", () => {
  const cart = new BulkBorrowCart();
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 3 }, 1, "C-01");
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 3 }, 1, "C-02");

  assert.deepEqual(cart.items(), [{ title_id: 12, quantity: 2, barcodes: ["C-01", "C-02"] }]);
});

test("quantity never exceeds the available copies", () => {
  const cart = new BulkBorrowCart();
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 2 }, 4);

  assert.equal(cart.totalQuantity(), 2);
  assert.equal(cart.items()[0].quantity, 2);
});

test("removing and clearing the cart leave no stale lines", () => {
  const cart = new BulkBorrowCart();
  cart.addTitle({ id: 12, title: "Clean Code", available_quantity: 2 });
  cart.addTitle({ id: 18, title: "Algorithms", available_quantity: 1 });

  cart.removeTitle(12);
  assert.equal(cart.has(12), false);
  cart.clear();
  assert.deepEqual(cart.items(), []);
});
