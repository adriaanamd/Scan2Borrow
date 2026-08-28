class RegistrationPageController {
  constructor() {
    this.form = document.getElementById("registration-form");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.bindCamera();
    this.form.addEventListener("submit", (event) => this.submit(event));
    const role = new URLSearchParams(location.search).get("role");
    if (role) document.getElementById("role").value = role;
  }
  bindCamera() {
    const video = document.getElementById("modal_cam"),
      canvas = document.getElementById("modal_snap"),
      preview = document.getElementById("modal_preview"),
      photo = document.getElementById("modal_photo_data");
    document.getElementById("modal_start")?.addEventListener("click", () =>
      navigator.mediaDevices
        .getUserMedia({ video: true })
        .then((stream) => {
          video.srcObject = stream;
          document.getElementById("modal_capture").classList.remove("d-none");
        })
        .catch(() => {}),
    );
    document.getElementById("modal_capture")?.addEventListener("click", () => {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext("2d").drawImage(video, 0, 0);
      photo.value = canvas.toDataURL("image/jpeg", 0.85);
      preview.src = photo.value;
      preview.classList.remove("d-none");
      document.getElementById("modal_retake").classList.remove("d-none");
    });
    document.getElementById("modal_retake")?.addEventListener("click", () => {
      photo.value = "";
      preview.classList.add("d-none");
    });
  }
  submit(event) {
    event.preventDefault();
    const body = new FormData(this.form);
    body.append("csrf", this.csrf);
    fetch("/scan2borrow/api/auth/register", { method: "POST", body })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok)
          throw new Error(response.errors?.[0] || "Registration failed.");
        location.href = "/scan2borrow/verify-otp";
      })
      .catch((error) => {
        const box = document.getElementById("form-error");
        box.hidden = false;
        box.textContent = error.message;
      });
  }
}
window.addEventListener(
  "DOMContentLoaded",
  () => new RegistrationPageController(),
);
