(function () {
  'use strict';

  function initializeApprovalSelectAll() {
    const master = document.getElementById('approval-select-all');
    const form = document.getElementById('approval-decision-form');
    if (!master || !form || master.dataset.bound === '1') return;
    master.dataset.bound = '1';

    const getBoxes = () => Array.from(form.querySelectorAll('.approval-select:not(:disabled)'));

    const syncMaster = () => {
      const boxes = getBoxes();
      const checked = boxes.filter((box) => box.checked).length;
      master.checked = boxes.length > 0 && checked === boxes.length;
      master.indeterminate = checked > 0 && checked < boxes.length;
    };

    master.addEventListener('change', () => {
      getBoxes().forEach((box) => {
        box.checked = master.checked;
      });
      syncMaster();
    });

    form.addEventListener('change', (event) => {
      if (event.target && event.target.matches('.approval-select')) syncMaster();
    });

    syncMaster();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApprovalSelectAll, { once: true });
  } else {
    initializeApprovalSelectAll();
  }
})();
