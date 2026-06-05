/**
 * Tiny hyperscript-style helper used by every component to build DOM trees
 * declaratively without a templating library.
 *
 * @param {string} tag
 * @param {Object} [attrs] - attributes; `class`, `html`, and `on*` handlers are special-cased
 * @param {...(Node|string|Array|null|false)} children
 * @returns {HTMLElement}
 */
export function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);

    for (const [key, value] of Object.entries(attrs)) {
        if (value === null || value === undefined || value === false) {
            continue;
        }

        if (key === 'class') {
            node.className = value;
        } else if (key === 'html') {
            node.innerHTML = value;
        } else if (key.startsWith('on') && typeof value === 'function') {
            node.addEventListener(key.slice(2).toLowerCase(), value);
        } else {
            node.setAttribute(key, value);
        }
    }

    for (const child of children.flat()) {
        if (child === null || child === undefined || child === false) {
            continue;
        }
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }

    return node;
}

/**
 * Replace the contents of a host element with a single rendered node.
 *
 * @param {HTMLElement} host
 * @param {Node} node
 */
export function mount(host, node) {
    host.replaceChildren(node);
}
