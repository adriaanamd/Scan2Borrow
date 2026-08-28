class AuthPageController {
  constructor() {
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.loginForm = document.querySelector(
      "#borrower-login-form, #staff-login-form",
    );
    this.registrationForms = document.querySelectorAll(
      "#studentRegForm, #teacherRegForm",
    );
    this.bindLogin();
    this.bindRegistration();
    this.bindModalCamera();
  }

  bindLogin() {
    this.loginForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      const formData = new FormData(this.loginForm);
      formData.append("csrf", this.csrf);
      const endpoint =
        this.loginForm.id === "staff-login-form"
          ? "/scan2borrow/api/auth/staff/login"
          : "/scan2borrow/api/auth/borrower/login";

      this.request(endpoint, formData)
        .then((response) => {
          if (!response.ok) {
            if (response.data?.registration_required) {
              this.showRegistrationModal(response.data.role);
              return;
            }
            throw new Error(response.errors?.[0] || "Login failed.");
          }
          window.location.href = response.data.redirect;
        })
        .catch((error) => this.showMessage("login-error", error.message));
    });
  }

  bindRegistration() {
    this.registrationForms.forEach((form) => {
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        formData.append("csrf", this.csrf);
        this.request("/scan2borrow/api/auth/register", formData)
          .then((response) => {
            if (!response.ok) {
              throw new Error(response.errors?.[0] || "Registration failed.");
            }
            window.location.href =
              response.data.redirect || "/scan2borrow/verify-otp";
          })
          .catch((error) =>
            this.showMessage(
              `${form.id === "studentRegForm" ? "student" : "teacher"}-register-error`,
              error.message,
            ),
          );
      });
    });
  }

  bindModalCamera() {
    const video = document.getElementById("modal_cam");
    const canvas = document.getElementById("modal_snap");
    const preview = document.getElementById("modal_preview");
    const photo = document.getElementById("modal_photo_data");
    const start = document.getElementById("modal_start");
    const capture = document.getElementById("modal_capture");
    const retake = document.getElementById("modal_retake");
    if (
      !video ||
      !canvas ||
      !preview ||
      !photo ||
      !start ||
      !capture ||
      !retake
    ) {
      return;
    }

    let stream = null;
    const stop = () => {
      if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
      }
    };

    start.addEventListener("click", () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        window.alert("Camera not supported");
        return;
      }
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
        })
        .catch(() => window.alert("Could not access camera"));
    });

    capture.addEventListener("click", () => {
      if (!stream) return;
      const width = video.videoWidth || 320;
      const height = video.videoHeight || 240;
      canvas.width = width;
      canvas.height = height;
      canvas.getContext("2d").drawImage(video, 0, 0, width, height);
      photo.value = canvas.toDataURL("image/jpeg", 0.85);
      preview.src = photo.value;
      preview.classList.remove("d-none");
      video.classList.add("d-none");
      capture.classList.add("d-none");
      retake.classList.remove("d-none");
      stop();
    });

    retake.addEventListener("click", () => {
      photo.value = "";
      preview.classList.add("d-none");
      retake.classList.add("d-none");
      start.classList.remove("d-none");
      start.click();
    });
    window.addEventListener("beforeunload", stop);
  }

  showRegistrationModal(role) {
    const id =
      role === "teacher" ? "teacherRegisterModal" : "studentRegisterModal";
    const modal = document.getElementById(id);
    if (modal && window.bootstrap) {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  }

  request(endpoint, body) {
    return fetch(endpoint, { method: "POST", body }).then((response) =>
      response.json(),
    );
  }

  showMessage(id, message) {
    const node = document.getElementById(id);
    if (!node) return;
    node.hidden = false;
    node.textContent = message;
  }
}

window.addEventListener("DOMContentLoaded", () => new AuthPageController());
