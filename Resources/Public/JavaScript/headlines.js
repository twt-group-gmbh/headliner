import AjaxDataHandler from '@typo3/backend/ajax-data-handler.js';
import Persistent from '@typo3/backend/storage/persistent.js';

// Remember in the settings of the backend user whether the box is collapsed. The PHP side reads it, so the box
// is rendered closed right away.
let wasOpen = document.querySelector('details.headliner')?.open ?? true;
document.addEventListener('toggle', (event) => {
  const box = event.target;
  // The browser also fires "toggle" for the state the box was rendered with; only a real change is saved.
  if (box instanceof HTMLDetailsElement && box.matches('.headliner') && box.open !== wasOpen) {
    wasOpen = box.open;
    Persistent.set('headliner.collapsed', box.open ? 0 : 1);
  }
}, true);

// Changes the tag of a header through the core's DataHandler, then reloads the page module so the structure
// and its problems are checked again.
document.addEventListener('change', (event) => {
  const select = event.target;
  if (!(select instanceof HTMLSelectElement) || !select.matches('.headliner select[data-headliner-edit]')) {
    return;
  }

  const [table, uid, field] = select.dataset.headlinerEdit.split(':');
  select.disabled = true;

  AjaxDataHandler.process({ data: { [table]: { [uid]: { [field]: select.value } } } })
    .then((result) => {
      if (result.hasErrors) {
        select.value = select.dataset.current;
        select.disabled = false;
        return;
      }
      window.location.reload();
    })
    .catch(() => {
      select.value = select.dataset.current;
      select.disabled = false;
    });
});
