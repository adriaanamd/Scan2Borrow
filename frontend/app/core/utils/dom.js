export function requiredElement(root, selector) {
  const element = root?.querySelector(selector);
  if (!element) {
    throw new Error('Required element not found: ' + selector);
  }

  return element;
}

export function optionalElement(root, selector) {
  return root?.querySelector(selector) || null;
}

export function setText(node, value) {
  if (node) {
    node.textContent = String(value ?? '');
  }

  return node;
}

export function clear(node) {
  node?.replaceChildren();
}
