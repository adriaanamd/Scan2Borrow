class CameraCapture {
  constructor(options = {}) {
    this.video = document.getElementById(options.videoId || "cam");
    this.canvas = document.getElementById(options.canvasId || "snap");
    this.preview = document.getElementById(options.previewId || "preview");
    this.field = document.getElementById(options.fieldId || "photo_data");
    this.message = document.getElementById(options.messageId || "cam-msg");
    this.startButton = document.getElementById(options.startId || "btn-start");
    this.captureButton = document.getElementById(
      options.captureId || "btn-capture",
    );
    this.retakeButton = document.getElementById(
      options.retakeId || "btn-retake",
    );
    this.stream = null;
    this.bindEvents();
    window.addEventListener("beforeunload", () => this.stop());
  }

  setMessage(text) {
    if (this.message) this.message.textContent = text || "";
  }
  stop() {
    if (this.stream) {
      this.stream.getTracks().forEach((track) => track.stop());
      this.stream = null;
    }
  }
  reset() {
    this.stop();
    this.video?.classList.remove("d-none");
    if (this.preview) {
      this.preview.classList.add("d-none");
      this.preview.src = "";
    }
    if (this.field) this.field.value = "";
    this.startButton?.classList.remove("d-none");
    this.captureButton?.classList.add("d-none");
    this.retakeButton?.classList.add("d-none");
    this.setMessage("");
  }
  start() {
    if (!navigator.mediaDevices?.getUserMedia) {
      this.setMessage("Camera not supported.");
      return;
    }
    navigator.mediaDevices
      .getUserMedia({ video: { facingMode: "user" }, audio: false })
      .then((stream) => {
        this.stop();
        this.stream = stream;
        this.video.srcObject = stream;
        this.video.play().catch(() => {});
        this.startButton?.classList.add("d-none");
        this.captureButton?.classList.remove("d-none");
        this.setMessage("Position yourself, then capture.");
      })
      .catch(() => this.setMessage("Could not access the camera."));
  }
  capture() {
    if (!this.stream) {
      this.setMessage("Camera is not active. Please start the camera first.");
      return;
    }
    const width = this.video.videoWidth || 320;
    const height = this.video.videoHeight || 240;
    this.canvas.width = width;
    this.canvas.height = height;
    this.canvas.getContext("2d").drawImage(this.video, 0, 0, width, height);
    const data = this.canvas.toDataURL("image/jpeg", 0.85);
    this.field.value = data;
    this.preview.src = data;
    this.preview.classList.remove("d-none");
    this.video.classList.add("d-none");
    this.stop();
    this.captureButton?.classList.add("d-none");
    this.retakeButton?.classList.remove("d-none");
    this.setMessage("Photo captured.");
  }
  retake() {
    this.reset();
    this.start();
  }
  bindEvents() {
    if (!this.startButton || !this.captureButton || !this.retakeButton) return;
    this.startButton.addEventListener("click", () => this.start());
    this.captureButton.addEventListener("click", () => this.capture());
    this.retakeButton.addEventListener("click", () => this.retake());
  }
}
