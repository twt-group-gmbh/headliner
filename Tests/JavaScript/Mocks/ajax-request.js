import { vi } from 'vitest';

// `get` and `post` stand for what the server answers; a test says what they return or reject with.
export const get = vi.fn();
export const post = vi.fn();

export default class AjaxRequest {
  constructor(url) {
    this.url = url;
    this.args = {};
  }

  withQueryArguments(args) {
    this.args = args;
    return this;
  }

  get() {
    return get(this.url, this.args);
  }

  post(data) {
    return post(this.url, data);
  }
}
