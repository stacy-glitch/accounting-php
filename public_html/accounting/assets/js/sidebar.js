(function () {
  'use strict';

  const toggles = document.querySelectorAll('[data-sidebar-toggle]');

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const group = toggle.closest('[data-sidebar-group]');
      if (!group) {
        return;
      }
      const subnav = group.querySelector('.sidebar__subnav');
      if (!subnav) {
        return;
      }

      const isHidden = subnav.hasAttribute('hidden');
      if (isHidden) {
        subnav.removeAttribute('hidden');
      } else {
        subnav.setAttribute('hidden', '');
      }
      toggle.setAttribute('aria-expanded', String(isHidden));
    });
  });
})();
