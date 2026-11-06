import AjaxDataHandler from '@typo3/backend/ajax-data-handler.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Persistent from '@typo3/backend/storage/persistent.js';

const SELECTOR = 'details.headliner';

// Remember in the settings of the backend user whether the box is collapsed. The PHP side reads it, so the box
// is rendered closed right away.
let wasOpen = document.querySelector(SELECTOR)?.open ?? true;
document.addEventListener('toggle', (event) => {
  const box = event.target;
  // The browser also fires "toggle" for the state the box was rendered with; only a real change is saved.
  if (box instanceof HTMLDetailsElement && box.matches('.headliner') && box.open !== wasOpen) {
    wasOpen = box.open;
    Persistent.set('headliner.collapsed', box.open ? 0 : 1);
  }
}, true);

// The page module only holds a placeholder: the page is checked here, afterwards, so the rendering of the frontend
// does not delay the page module. This also happens while the box is collapsed, so its summary shows the problems.
const url = document.querySelector(SELECTOR)?.dataset.headlinerUrl;
const errorText = document.querySelector(SELECTOR)?.dataset.headlinerError ?? '';

// Gets the box from the server and puts it in the place of the current one. Keeps what the user did meanwhile: an
// open or closed box.
async function load() {
  const current = document.querySelector(SELECTOR);
  if (!url || !current) {
    return false;
  }
  try {
    const response = await new AjaxRequest(url)
      .withQueryArguments({ returnUrl: window.location.pathname + window.location.search })
      .get();
    const html = (await response.resolve()).trim();
    if (html === '') {
      // nothing to show for this page
      current.remove();
      return true;
    }
    const template = document.createElement('template');
    template.innerHTML = html;
    const box = template.content.firstElementChild;
    box.open = current.open;
    wasOpen = box.open;
    current.replaceWith(box);
    return true;
  } catch {
    const body = current.querySelector('.card-body');
    if (body) {
      body.replaceChildren(Object.assign(document.createElement('div'), {
        className: 'alert alert-warning',
        textContent: errorText,
      }));
    }
    current.querySelector('.headliner__loading-badge')?.remove();
    return false;
  }
}

load();

// The page module changes content without loading the page again: hiding or showing an element, moving or
// dragging it. The structure changes with it, so the box is loaded again. Several changes in a row (or a change
// that is followed by a reload of the page module) give one request; one that arrives while the box is loading
// starts another load afterwards, as the first one may have read the page before the change.
let reloadTimer;
let loading = false;
let reloadAgain = false;
function reloadSoon() {
  clearTimeout(reloadTimer);
  reloadTimer = setTimeout(async () => {
    if (loading) {
      reloadAgain = true;
      return;
    }
    loading = true;
    await load();
    loading = false;
    if (reloadAgain) {
      reloadAgain = false;
      reloadSoon();
    }
  }, 200);
}
// an element is hidden or shown (the core's own route, which does not fire the event of the DataHandler)
document.addEventListener('typo3:page-layout:hidden-content-count-changed', reloadSoon);
// a record was changed with the core's DataHandler (drag and drop of an element)
document.addEventListener('typo3:datahandler:process', reloadSoon);

// Changes the tag of a header through the core's DataHandler, then loads the box again so the structure and its
// problems are checked again.
document.addEventListener('change', (event) => {
  const select = event.target;
  if (!(select instanceof HTMLSelectElement) || !select.matches('.headliner select[data-headliner-edit]')) {
    return;
  }

  const [table, uid, field] = select.dataset.headlinerEdit.split(':');
  select.disabled = true;
  const restore = () => {
    select.value = select.dataset.current;
    select.disabled = false;
  };

  AjaxDataHandler.process({ data: { [table]: { [uid]: { [field]: select.value } } } })
    .then((result) => {
      if (result.hasErrors) {
        restore();
        return;
      }
      // a failed load shows its message in the box; the old box stays until the new one has arrived
      return load().then((loaded) => loaded || restore());
    })
    .catch(restore);
});
