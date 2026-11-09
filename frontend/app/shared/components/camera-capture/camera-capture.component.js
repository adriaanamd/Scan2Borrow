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
    fileInputId = 'camera-file',
    nativeButtonId = 'btn-native-camera',
    fileReaderFactory = () => new FileReader(),
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
    this.fileInput = document.getElementById(fileInputId);
    this.nativeButton = document.getElementById(nativeButtonId);
    this.fileReaderFactory = fileReaderFactory;
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
    this.stream?.getTracks?.().forEach((track) => track.stop());
    this.stream = null;
    if (this.video) this.video.srcObject = null;
  }

  reset() {
    this.stop();
    this.video?.classList.remove('d-none');
    if (this.preview) {
      this.preview.classList.add('d-none');
      this.preview.src = '';
    }
    if (this.field) this.field.value = '';
    if (this.fileInput) this.fileInput.value = '';
    this.startButton?.classList.remove('d-none');
    this.captureButton?.classList.add('d-none');
    this.retakeButton?.classList.add('d-none');
    this.setMessage('');
  }

  start() {
    if (!this.navigator.mediaDevices?.getUserMedia) {
      this.setMessage('Live camera unavailable. Use the phone camera instead.');
      this.openNativeCapture();
      return Promise.resolve();
    }
    return this.navigator.mediaDevices
      .getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then((stream) => {
        this.stop();
        this.stream = stream;
        if (this.video) {
          this.video.autoplay = true;
          this.video.muted = true;
          this.video.playsInline = true;
        }
        this.video.srcObject = stream;
        this.video.play?.().catch?.(() => {});
        this.startButton?.classList.add('d-none');
        this.captureButton?.classList.remove('d-none');
        this.setMessage('Position yourself, then capture.');
      })
      .catch(() => this.setMessage('Could not access live camera. Use the phone camera instead.'));
  }

  openNativeCapture() {
    this.fileInput?.click?.();
  }

  applyPhoto(dataUrl, message = 'Photo captured.') {
    if (!dataUrl || !this.field || !this.preview) return;
    this.field.value = dataUrl;
    this.preview.src = dataUrl;
    this.preview.classList.remove('d-none');
    this.video?.classList.add('d-none');
    this.stop();
    this.startButton?.classList.add('d-none');
    this.captureButton?.classList.add('d-none');
    this.retakeButton?.classList.remove('d-none');
    this.setMessage(message);
  }

  handleFileChange(event) {
    const input = event?.target || this.fileInput;
    const file = input?.files?.[0];
    if (!file) return Promise.resolve(null);

    return new Promise((resolve) => {
      let reader;
      try {
        reader = this.fileReaderFactory();
      } catch {
        this.setMessage('Unable to read the photo. Try again.');
        if (input) input.value = '';
        resolve(null);
        return;
      }
      if (!reader?.readAsDataURL) {
        this.setMessage('Unable to read the photo. Try again.');
        if (input) input.value = '';
        resolve(null);
        return;
      }
      reader.onload = (loadEvent) => {
        const dataUrl = loadEvent?.target?.result;
        if (typeof dataUrl === 'string' && dataUrl) this.applyPhoto(dataUrl);
        else this.setMessage('Unable to read the photo. Try again.');
        if (input) input.value = '';
        resolve(typeof dataUrl === 'string' && dataUrl ? dataUrl : null);
      };
      reader.onerror = () => {
        this.setMessage('Unable to read the photo. Try again.');
        if (input) input.value = '';
        resolve(null);
      };
      try {
        reader.readAsDataURL(file);
      } catch {
        this.setMessage('Unable to read the photo. Try again.');
        if (input) input.value = '';
        resolve(null);
      }
    });
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
    this.applyPhoto(data);
  }

  retake() {
    this.reset();
    return this.start();
  }

  bindEvents() {
    this.listen(this.startButton, 'click', () => this.start());
    this.listen(this.captureButton, 'click', () => this.capture());
    this.listen(this.retakeButton, 'click', () => this.retake());
    this.listen(this.fileInput, 'change', (event) => this.handleFileChange(event));
    this.listen(this.nativeButton, 'click', () => this.openNativeCapture());
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    this.window.removeEventListener?.('beforeunload', this.beforeUnload);
    this.stop();
  }
}
