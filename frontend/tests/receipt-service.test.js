import test from 'node:test';
import assert from 'node:assert/strict';
import { ReceiptService } from '../features/student/services/receipt.service.js';

test('ReceiptService preserves code query and compatibility receipt payload', async () => {
  const calls = [];
  const service = new ReceiptService({
    api: { get: async (path, params) => { calls.push({ path, params }); return { ok: true, data: { transaction_code: 'TX-1' } }; } },
  });

  const response = await service.load('TX-1');
  assert.deepEqual(response.data, { transaction_code: 'TX-1' });
  assert.deepEqual(service.compatibilityPayload('TX-1'), { code: 'TX-1' });
  assert.deepEqual(calls, [{ path: '/scan2borrow/api/receipt', params: { code: 'TX-1' } }]);
});
