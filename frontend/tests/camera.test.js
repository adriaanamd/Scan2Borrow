import test from 'node:test';
import assert from 'node:assert/strict';
import { CameraCaptureComponent } from '../app/shared/components/camera-capture/camera-capture.component.js';

test('CameraCaptureComponent preserves user camera constraints and JPEG capture flow', async () => {
  let constraints;
  const tracks = [{ stopped: false, stop() { this.stopped = true; } }];
  const stream = { getTracks: () => tracks };
  const classes = () => ({ values: new Set(), add(name) { this.values.add(name); }, remove(name) { this.values.delete(name); } });
  const video = { classList: classes(), videoWidth: 640, videoHeight: 480, play: async () => {} };
  const preview = { classList: classes(), src: '' };
  const field = { value: '' };
  const message = { textContent: '' };
  const start = { classList: classes(), addEventListener() {} };
  const capture = { classList: classes(), addEventListener() {} };
  const retake = { classList: classes(), addEventListener() {} };
  const canvas = {
    getContext: () => ({ drawImage() {} }),
    toDataURL: (type, quality) => { assert.equal(type, 'image/jpeg'); assert.equal(quality, 0.85); return 'data:image/jpeg'; },
  };
  const document = {
    getElementById(id) { return { cam: video, snap: canvas, preview, photo_data: field, 'cam-msg': message, 'btn-start': start, 'btn-capture': capture, 'btn-retake': retake }[id] || null; },
  };
  const window = { addEventListener() {}, removeEventListener() {} };
  const navigator = { mediaDevices: { getUserMedia: async (value) => { constraints = value; return stream; } } };
  const component = new CameraCaptureComponent({}, { document, window, navigator });

  await component.start();
  component.capture();
  assert.deepEqual(constraints, { video: { facingMode: 'user' }, audio: false });
  assert.equal(field.value, 'data:image/jpeg');
  assert.equal(tracks[0].stopped, true);
  await component.destroy();
});

test('CameraCaptureComponent opens the native photo input without live camera support', async () => {
  const classes = () => ({ values: new Set(), add(name) { this.values.add(name); }, remove(name) { this.values.delete(name); } });
  const video = { classList: classes(), videoWidth: 640, videoHeight: 480, play: async () => {} };
  const preview = { classList: classes(), src: '' };
  const field = { value: '' };
  const message = { textContent: '' };
  const start = { classList: classes(), addEventListener() {} };
  const capture = { classList: classes(), addEventListener() {} };
  const retake = { classList: classes(), addEventListener() {} };
  const fileInput = { value: '', click() { this.clicked = true; }, addEventListener() {} };
  const nativeButton = { classList: classes(), addEventListener() {} };
  const canvas = { getContext: () => ({ drawImage() {} }), toDataURL: () => 'data:image/jpeg' };
  const document = {
    getElementById(id) {
      return {
        cam: video,
        snap: canvas,
        preview,
        photo_data: field,
        'cam-msg': message,
        'btn-start': start,
        'btn-capture': capture,
        'btn-retake': retake,
        'camera-file': fileInput,
        'btn-native-camera': nativeButton,
      }[id] || null;
    },
  };
  const component = new CameraCaptureComponent({}, {
    document,
    window: { addEventListener() {}, removeEventListener() {} },
    navigator: {},
  });

  await component.start();

  assert.equal(fileInput.clicked, true);
  assert.match(message.textContent, /phone camera/i);
});

test('CameraCaptureComponent applies a native photo as the same form data URL', async () => {
  const classes = () => ({ values: new Set(), add(name) { this.values.add(name); }, remove(name) { this.values.delete(name); } });
  const video = { classList: classes(), videoWidth: 640, videoHeight: 480 };
  const preview = { classList: classes(), src: '' };
  const field = { value: '' };
  const message = { textContent: '' };
  const start = { classList: classes(), addEventListener() {} };
  const capture = { classList: classes(), addEventListener() {} };
  const retake = { classList: classes(), addEventListener() {} };
  const fileInput = { value: '', addEventListener() {} };
  const nativeButton = { classList: classes(), addEventListener() {} };
  const canvas = { getContext: () => ({ drawImage() {} }), toDataURL: () => 'data:image/jpeg' };
  const document = {
    getElementById(id) {
      return {
        cam: video,
        snap: canvas,
        preview,
        photo_data: field,
        'cam-msg': message,
        'btn-start': start,
        'btn-capture': capture,
        'btn-retake': retake,
        'camera-file': fileInput,
        'btn-native-camera': nativeButton,
      }[id] || null;
    },
  };
  const reader = {
    readAsDataURL() {
      this.onload({ target: { result: 'data:image/jpeg;base64,photo' } });
    },
  };
  const component = new CameraCaptureComponent({}, {
    document,
    window: { addEventListener() {}, removeEventListener() {} },
    navigator: {},
    fileReaderFactory: () => reader,
  });

  await component.handleFileChange({ target: { files: [{ name: 'portrait.jpg' }] } });

  assert.equal(field.value, 'data:image/jpeg;base64,photo');
  assert.equal(preview.src, field.value);
  assert.match(message.textContent, /captured/i);
});
