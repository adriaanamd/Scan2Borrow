class RegistrationPageController {
  constructor() {
    this.form = document.getElementById("reg-form");
    this.role = document.getElementById("role_select");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.bindRoleSelection();
    this.bindCamera();
    this.form?.addEventListener("submit", (event) => this.submit(event));

    const preselectedRole = new URLSearchParams(window.location.search).get(
      "role",
    );
    if (preselectedRole) {
      this.role.value = preselectedRole;
      this.toggleFields();
    } else {
      this.toggleFields();
    }
  }

  bindRoleSelection() {
    this.role?.addEventListener("change", () => this.toggleFields());
    document.getElementById("chooseStudent")?.addEventListener("click", () => {
      this.selectRole("student");
    });
    document.getElementById("chooseTeacher")?.addEventListener("click", () => {
      this.selectRole("teacher");
    });
    document.getElementById("chooseGuest")?.addEventListener("click", () => {
      window.location.href = "/scan2borrow/guest/registration";
    });
  }

  selectRole(role) {
    this.role.value = role;
    this.toggleFields();
  }

  toggleFields() {
    const isStudent = this.role?.value === "student";
    const isTeacher = this.role?.value === "teacher";
    document.querySelectorAll(".student-only").forEach((field) => {
      field.classList.toggle("d-none", !isStudent);
    });
    document.querySelectorAll(".teacher-only").forEach((field) => {
      field.classList.toggle("d-none", !isTeacher);
    });
    document.querySelectorAll(".registration-role-choice").forEach((choice) => {
      const selected = choice.dataset.role === this.role?.value;
      choice.classList.toggle("is-selected", selected);
      choice.setAttribute("aria-pressed", selected ? "true" : "false");
    });
    const label = document.getElementById("selected-role-label");
    if (label) {
      label.textContent = isStudent ? "Student" : isTeacher ? "Teacher" : "Select a role";
    }
  }

  bindCamera() {
    const video = document.getElementById("cam");
    const canvas = document.getElementById("snap");
    const preview = document.getElementById("preview");
    const field = document.getElementById("photo_data");
    const message = document.getElementById("cam-msg");
    const start = document.getElementById("btn-start");
    const capture = document.getElementById("btn-capture");
    const retake = document.getElementById("btn-retake");
    if (
      !video ||
      !canvas ||
      !preview ||
      !field ||
      !message ||
      !start ||
      !capture ||
      !retake
    )
      return;

    let stream = null;
    const stop = () => {
      if (!stream) return;
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    };

    start.addEventListener("click", () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        message.textContent = "Camera not supported on this browser.";
        return;
      }
      message.textContent = "Starting camera...";
      navigator.mediaDevices
        .getUserMedia({ video: { facingMode: "user" }, audio: false })
        .then((nextStream) => {
          stream = nextStream;
          video.srcObject = stream;
          video.classList.remove("d-none");
          preview.classList.add("d-none");
          start.classList.add("d-none");
          capture.classList.remove("d-none");
          retake.classList.add("d-none");
          message.textContent =
            "Position your face in the frame, then Capture.";
        })
        .catch(() => {
          message.textContent =
            "Could not access the camera. Please allow camera permission.";
        });
    });

    capture.addEventListener("click", () => {
      if (!stream) return;
      const width = video.videoWidth || 320;
      const height = video.videoHeight || 240;
      canvas.width = width;
      canvas.height = height;
      canvas.getContext("2d").drawImage(video, 0, 0, width, height);
      field.value = canvas.toDataURL("image/jpeg", 0.85);
      preview.src = field.value;
      preview.classList.remove("d-none");
      video.classList.add("d-none");
      capture.classList.add("d-none");
      retake.classList.remove("d-none");
      stop();
      message.textContent = "Photo captured. Click Retake to redo.";
    });

    retake.addEventListener("click", () => {
      field.value = "";
      preview.classList.add("d-none");
      retake.classList.add("d-none");
      start.classList.remove("d-none");
      start.click();
    });
    window.addEventListener("beforeunload", stop);
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
        window.location.href =
          response.data.redirect || "/scan2borrow/verify-otp";
      })
      .catch((error) => {
        const box = document.getElementById("form-error");
        if (!box) return;
        box.hidden = false;
        box.textContent = error.message;
      });
  }
}

window.addEventListener(
  "DOMContentLoaded",
  () => new RegistrationPageController(),
);
