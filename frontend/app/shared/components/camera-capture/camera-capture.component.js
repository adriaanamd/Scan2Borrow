export class CameraCaptureComponent {
  constructor(root, {
    document = globalThis.document,
    window = globalThis.window,
    navigator = globalThis.navigator,
    videoId = 'cam',
    canvasId = 'snap',
    previewId = 'preview',
    fieldId = 'photo_data',
    messageId = 'cam-msg',
    startId = 'btn-start',
    captureId = 'btn-capture',
    retakeId = 'btn-retake',
  } = {}) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.navigator = navigator;
    this.video = document.getElementById(videoId);
    this.canvas = document.getElementById(canvasId);
    this.preview = document.getElementById(previewId);
    this.field = document.getElementById(fieldId);
    this.message = document.getElementById(messageId);
    this.startButton = document.getElementById(startId);
    this.captureButton = document.getElementById(captureId);
    this.retakeButton = document.getElementById(retakeId);
    this.stream = null;
    this.listeners = [];
    this.beforeUnload = () => this.stop();
    this.bindEvents();
    this.window.addEventListener?.('beforeunload', this.beforeUnload);
  }

  listen(target, eventName, callback) {
    if (!target?.addEventListener) return;
    target.addEventListener(eventName, callback);
    this.listeners.push(() => target.removeEventListener?.(eventName, callback));
  }

  setMessage(text) {
    if (this.message) this.message.textContent = text || '';
  }

  stop() {
    if (!this.stream) return;
    this.stream.getTracks().forEach((track) => track.stop());
    this.stream = null;
  }

  reset() {
    this.stop();
    this.video?.classList.remove('d-none');
    if (this.preview) {
      this.preview.classList.add('d-none');
      this.preview.src = '';
    }
    if (this.field) this.field.value = '';
    this.startButton?.classList.remove('d-none');
    this.captureButton?.classList.add('d-none');
    this.retakeButton?.classList.add('d-none');
    this.setMessage('');
  }

  start() {
    if (!this.navigator.mediaDevices?.getUserMedia) {
      this.setMessage('Camera not supported.');
      return Promise.resolve();
    }
    return this.navigator.mediaDevices
      .getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then((stream) => {
        this.stop();
        this.stream = stream;
        this.video.srcObject = stream;
        this.video.play?.().catch?.(() => {});
        this.startButton?.classList.add('d-none');
        this.captureButton?.classList.remove('d-none');
        this.setMessage('Position yourself, then capture.');
      })
      .catch(() => this.setMessage('Could not access the camera.'));
  }

  capture() {
    if (!this.stream) {
      this.setMessage('Camera is not active. Please start the camera first.');
      return;
    }
    const width = this.video.videoWidth || 320;
    const height = this.video.videoHeight || 240;
    this.canvas.width = width;
    this.canvas.height = height;
    this.canvas.getContext('2d').drawImage(this.video, 0, 0, width, height);
    const data = this.canvas.toDataURL('image/jpeg', 0.85);
    this.field.value = data;
    this.preview.src = data;
    this.preview.classList.remove('d-none');
    this.video.classList.add('d-none');
    this.stop();
    this.captureButton?.classList.add('d-none');
    this.retakeButton?.classList.remove('d-none');
    this.setMessage('Photo captured.');
  }

  retake() {
    this.reset();
    return this.start();
  }

  bindEvents() {
    if (!this.startButton || !this.captureButton || !this.retakeButton) return;
    this.listen(this.startButton, 'click', () => this.start());
    this.listen(this.captureButton, 'click', () => this.capture());
    this.listen(this.retakeButton, 'click', () => this.retake());
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    this.window.removeEventListener?.('beforeunload', this.beforeUnload);
    this.stop();
  }
}
