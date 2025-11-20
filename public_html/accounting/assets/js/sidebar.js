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

  addLogoutButton();

  function addLogoutButton() {
    if (document.querySelector('[data-logout-button]')) {
      return;
    }
    const append = () => {
      if (document.querySelector('[data-logout-button]')) {
        return;
      }
      const link = document.createElement('a');
      link.className = "layout__logout";
      link.href = '/accounting/logout.php';
      link.textContent = '登出';
      link.setAttribute('data-logout-button', 'true');
      link.setAttribute('title', '登出');
      Object.assign(link.style,{position:'fixed',top:'16px',right:'16px',background:'#d9a07d',color:'#fff6f0',padding:'8px 18px',borderRadius:'20px',textDecoration:'none',zIndex:'9999',display:'inline-flex'});
      document.body.appendChild(link);
    };
    if (document.body) {
      append();
    } else {
      document.addEventListener('DOMContentLoaded', append, { once: true });
    }
  }
})();

