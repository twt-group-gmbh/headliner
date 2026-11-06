// jsdom has no CSS.escape, which the page module script uses for the group of a heading.
globalThis.CSS ??= {};
globalThis.CSS.escape ??= (value) => String(value).replace(/["\\]/g, '\\$&');
