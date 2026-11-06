import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The script has no exports: it runs when it is imported. Every test puts the page module in the DOM, resets the
// modules and imports the script (and the mocks it uses) again, and takes the listeners it put on the document away
// afterwards. `m` holds the mocks of the current test.
let m;
let listeners = [];

async function load(first) {
  vi.resetModules();
  m = {
    get: (await import('./Mocks/ajax-request.js')).get,
    post: (await import('./Mocks/ajax-request.js')).post,
    process: (await import('./Mocks/ajax-data-handler.js')).default.process,
    warning: (await import('./Mocks/notification.js')).default.warning,
    set: (await import('./Mocks/persistent.js')).default.set,
  };
  m.get.mockReset();
  if (first instanceof Error) {
    m.get.mockRejectedValue(first);
  } else {
    m.get.mockResolvedValue(first);
  }
  await import('../../Resources/Public/JavaScript/headlines.js');
}

const settle = async () => {
  for (let i = 0; i < 6; i++) {
    await vi.advanceTimersByTimeAsync(0);
  }
};

const box = ({ open = true, body = '' } = {}) => `<details class="headliner"${open ? ' open' : ''}
  data-headliner-url="/route" data-headliner-error="Could not load" data-headliner-rte-url="/rte"
  data-headliner-rte-error="RTE failed"><summary>Heading structure</summary>
  <div class="card-body">${body}<span class="headliner__loading-badge"></span></div></details>`;

const answer = (html) => ({ resolve: () => Promise.resolve(html) });

const select = (attributes = '') => `<select class="form-select" data-headliner-edit="tt_content:82:header_tag"
  data-current="h2" ${attributes}><option value="h2" selected>H2</option><option value="h3">H3</option></select>`;

async function mount({ placeholder = box(), served = box() } = {}) {
  document.body.innerHTML = `<div class="t3js-page-ce" data-uid="82"></div><div id="module">${placeholder}</div>`;
  await load(answer(served));
  await settle();
}

const choose = (element, value) => {
  element.value = value;
  element.dispatchEvent(new Event('change', { bubbles: true }));
};

beforeEach(() => {
  vi.useFakeTimers();
  listeners = [];
  const add = document.addEventListener.bind(document);
  vi.spyOn(document, 'addEventListener').mockImplementation((type, listener, options) => {
    listeners.push([type, listener, options]);
    add(type, listener, options);
  });
});

afterEach(() => {
  listeners.forEach(([type, listener, options]) => document.removeEventListener(type, listener, options));
  vi.restoreAllMocks();
  vi.useRealTimers();
});

describe('loading the box', () => {
  it('puts the box of the server in the place of the placeholder, with the url of the page module', async () => {
    window.history.replaceState({}, '', '/module?id=2');
    await mount({ served: box({ body: '<p id="served"></p>' }) });

    expect(m.get).toHaveBeenCalledWith('/route', { returnUrl: '/module?id=2' });
    expect(document.querySelectorAll('.headliner')).toHaveLength(1);
    expect(document.querySelector('#served')).not.toBeNull();
    expect(document.querySelector('.headliner__loading-badge')).not.toBeNull();
  });

  it('keeps the box closed that the user closed while it was loading', async () => {
    await mount({ placeholder: box({ open: false }), served: box({ open: true }) });

    expect(document.querySelector('.headliner').open).toBe(false);
  });

  it('removes the box if the server has nothing to show', async () => {
    await mount({ served: '   ' });

    expect(document.querySelector('.headliner')).toBeNull();
  });

  it('says so in the box if it cannot be loaded, and takes the loading badge away', async () => {
    document.body.innerHTML = `<div>${box()}</div>`;
  await load(new Error('500'));
    await settle();

    expect(document.querySelector('.card-body .alert-warning').textContent).toBe('Could not load');
    expect(document.querySelector('.headliner__loading-badge')).toBeNull();
  });
});

describe('collapsing the box', () => {
  it('saves only a real change, not the state the box was drawn with', async () => {
    await mount();
    const details = document.querySelector('.headliner');

    details.dispatchEvent(new Event('toggle'));
    expect(m.set).not.toHaveBeenCalled();

    details.open = false;
    details.dispatchEvent(new Event('toggle'));
    expect(m.set).toHaveBeenLastCalledWith('headliner.collapsed', 1);

    details.dispatchEvent(new Event('toggle'));
    expect(m.set).toHaveBeenCalledTimes(1);

    details.open = true;
    details.dispatchEvent(new Event('toggle'));
    expect(m.set).toHaveBeenLastCalledWith('headliner.collapsed', 0);
  });
});

describe('loading the box again after a change in the page module', () => {
  const event = () => document.dispatchEvent(new Event('typo3:page-layout:hidden-content-count-changed'));

  it('makes one request for several changes in a row', async () => {
    await mount();
    expect(m.get).toHaveBeenCalledTimes(1);

    event();
    event();
    event();
    await vi.advanceTimersByTimeAsync(199);
    expect(m.get).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1);
    await settle();

    expect(m.get).toHaveBeenCalledTimes(2);
  });

  it('reacts to a record that was changed with the DataHandler as well', async () => {
    await mount();

    document.dispatchEvent(new Event('typo3:datahandler:process'));
    await vi.advanceTimersByTimeAsync(200);
    await settle();

    expect(m.get).toHaveBeenCalledTimes(2);
  });

  it('loads once more after a load that a change arrived during', async () => {
    await mount();
    let finish;
    m.get.mockReturnValueOnce(new Promise((resolve) => { finish = () => resolve(answer(box())); }));

    event();
    await vi.advanceTimersByTimeAsync(200);
    expect(m.get).toHaveBeenCalledTimes(2);

    // a change while the second load has not arrived: no request yet, but one after it
    event();
    await vi.advanceTimersByTimeAsync(200);
    expect(m.get).toHaveBeenCalledTimes(2);

    finish();
    await settle();
    await vi.advanceTimersByTimeAsync(200);
    await settle();

    expect(m.get).toHaveBeenCalledTimes(3);
  });
});

describe('changing the tag of a header', () => {
  const served = () => box({ body: select() });

  it('saves it through the DataHandler and loads the box again', async () => {
    await mount({ served: served() });
    m.process.mockResolvedValue({ hasErrors: false });

    choose(document.querySelector('select'), 'h3');
    await settle();

    expect(m.process).toHaveBeenCalledWith({ data: { tt_content: { 82: { header_tag: 'h3' } } } });
    expect(m.get).toHaveBeenCalledTimes(2);
  });

  it('puts the old tag back, and does not load the box again, if the DataHandler reports errors', async () => {
    await mount({ served: served() });
    const element = document.querySelector('select');
    m.process.mockResolvedValue({ hasErrors: true });

    choose(element, 'h3');
    expect(element.disabled).toBe(true);
    await settle();

    expect(element.value).toBe('h2');
    expect(element.disabled).toBe(false);
    expect(m.get).toHaveBeenCalledTimes(1);
  });

  it('puts the old tag back if the request fails', async () => {
    await mount({ served: served() });
    const element = document.querySelector('select');
    m.process.mockRejectedValue(new Error('network'));

    choose(element, 'h3');
    await settle();

    expect(element.value).toBe('h2');
    expect(element.disabled).toBe(false);
  });

  it('puts the old tag back if the box cannot be loaded again afterwards', async () => {
    await mount({ served: served() });
    const element = document.querySelector('select');
    m.process.mockResolvedValue({ hasErrors: false });
    m.get.mockRejectedValue(new Error('500'));

    choose(element, 'h3');
    await settle();

    expect(element.value).toBe('h2');
    expect(element.disabled).toBe(false);
  });

  it('leaves every other select alone: one without a field in the box, one outside of the box', async () => {
    await mount({ served: box({ body: '<select class="plain"><option>a</option></select>' }) });
    document.body.insertAdjacentHTML('beforeend', '<select class="outside" data-headliner-edit="x:1:y"><option>a</option></select>');

    choose(document.querySelector('.plain'), 'a');
    choose(document.querySelector('.outside'), 'a');
    await settle();

    expect(m.process).not.toHaveBeenCalled();
  });
});

describe('changing the tag of a heading in the rich text', () => {
  const rte = () => box({
    body: select('data-headliner-rte="tt_content:82:bodytext:1" data-headliner-text="Hello"'),
  });

  it('sends the place of the heading to the route of the extension and loads the box again', async () => {
    await mount({ served: rte() });
    m.post.mockResolvedValue(answer(''));

    choose(document.querySelector('select'), 'h3');
    await settle();

    expect(m.post).toHaveBeenCalledWith('/rte', {
      table: 'tt_content',
      uid: '82',
      field: 'bodytext',
      ordinal: '1',
      tag: 'h3',
      text: 'Hello',
    });
    expect(m.process).not.toHaveBeenCalled();
    expect(m.get).toHaveBeenCalledTimes(2);
  });

  it('says so, shows what is in the text now and puts the old tag back if the route refuses', async () => {
    await mount({ served: rte() });
    const element = document.querySelector('select');
    m.post.mockRejectedValue(new Error('409'));

    choose(element, 'h3');
    await settle();

    expect(m.warning).toHaveBeenCalledWith('RTE failed', '', 8);
    expect(m.get).toHaveBeenCalledTimes(2);
    expect(element.value).toBe('h2');
    expect(element.disabled).toBe(false);
  });
});

describe('hovering a heading', () => {
  const rows = box({
    body: `<ol>
      <li id="a" class="headliner__item" data-headliner-group="82" style="--headliner-hue: 120">a</li>
      <li id="b" class="headliner__item" data-headliner-group="82">b</li>
      <li id="c" class="headliner__item">c</li></ol>`,
  });
  const hover = (id) => document.getElementById(id).dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));

  it('marks the headings of the same content element and the element in the page module', async () => {
    await mount({ served: rows });

    hover('a');

    expect(document.getElementById('a').classList.contains('headliner__item--related')).toBe(true);
    expect(document.getElementById('b').classList.contains('headliner__item--related')).toBe(true);
    expect(document.getElementById('c').classList.contains('headliner__item--related')).toBe(false);
    const element = document.querySelector('.t3js-page-ce');
    expect(element.classList.contains('headliner-related-element')).toBe(true);
    expect(element.style.getPropertyValue('--headliner-hue')).toBe('120');
  });

  it('takes the marks away when the pointer moves to a heading without a group', async () => {
    await mount({ served: rows });

    hover('a');
    hover('c');

    expect(document.querySelector('.headliner__item--related')).toBeNull();
    expect(document.querySelector('.headliner-related-element')).toBeNull();
  });
});
