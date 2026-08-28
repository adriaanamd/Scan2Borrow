class Scan2BorrowMedia {
  static applicationPath() {
    return "/scan2borrow";
  }

  static resolve(value) {
    if (value === null || value === undefined) return "";

    const normalized = String(value).trim().replace(/\\/g, "/");
    if (!normalized) return "";

    if (/^(data:image\/|blob:|https?:\/\/|\/\/)/i.test(normalized)) {
      return normalized;
    }

    const uploadMarker = "/uploads/";
    const uploadIndex = normalized.toLowerCase().indexOf(uploadMarker);
    if (uploadIndex >= 0) {
      return `${Scan2BorrowMedia.applicationPath()}${normalized.slice(uploadIndex)}`;
    }
    if (normalized.toLowerCase().startsWith("uploads/")) {
      return `${Scan2BorrowMedia.applicationPath()}/${normalized}`;
    }

    if (normalized.toLowerCase().startsWith("/scan2borrow/")) {
      return normalized;
    }
    if (normalized.toLowerCase().startsWith("frontend/")) {
      return `${Scan2BorrowMedia.applicationPath()}/${normalized}`;
    }

    return normalized.startsWith("/")
      ? normalized
      : `${Scan2BorrowMedia.applicationPath()}/${normalized}`;
  }
}

window.Scan2BorrowMedia = Scan2BorrowMedia;
