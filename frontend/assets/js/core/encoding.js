class LegacyEncodingRepair {
  constructor() {
    this.windows1252 = {
      0x20ac: 0x80,
      0x201a: 0x82,
      0x192: 0x83,
      0x201e: 0x84,
      0x2026: 0x85,
      0x2020: 0x86,
      0x2021: 0x87,
      0x2c6: 0x88,
      0x2030: 0x89,
      0x160: 0x8a,
      0x2039: 0x8b,
      0x152: 0x8c,
      0x17d: 0x8e,
      0x2018: 0x91,
      0x2019: 0x92,
      0x201c: 0x93,
      0x201d: 0x94,
      0x2022: 0x95,
      0x2013: 0x96,
      0x2014: 0x97,
      0x2dc: 0x98,
      0x2122: 0x99,
      0x161: 0x9a,
      0x203a: 0x9b,
      0x153: 0x9c,
      0x17e: 0x9e,
      0x178: 0x9f,
    };
  }

  start() {
    this.repair(document.body);
    new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => this.repair(node));
      });
    }).observe(document.body, { childList: true, subtree: true });
  }

  repair(root) {
    if (root.nodeType === Node.TEXT_NODE) {
      root.nodeValue = this.decode(root.nodeValue || "");
      return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      node.nodeValue = this.decode(node.nodeValue || "");
    });
  }

  decode(value) {
    let repaired = value;
    for (let attempt = 0; attempt < 2; attempt += 1) {
      if (!/[ÃÂâð]/.test(repaired)) break;
      const bytes = new Uint8Array(
        [...repaired].map((character) => {
          const code = character.codePointAt(0);
          return code <= 255 ? code : (this.windows1252[code] ?? 63);
        }),
      );
      const next = new TextDecoder("utf-8").decode(bytes);
      if (next === repaired) break;
      repaired = next;
    }
    return repaired;
  }
}

window.addEventListener("DOMContentLoaded", () => {
  new LegacyEncodingRepair().start();
});
