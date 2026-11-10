/* Scripts communs */
(function () {
  'use strict';

  // Confirmation de suppression via modale Bootstrap
  const modalEl = document.getElementById('confirmModal');
  let pendingForm = null;
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    document.addEventListener('submit', function (e) {
      const f = e.target;
      if (f.matches('form[data-confirm]') && !f.dataset.confirmed) {
        e.preventDefault();
        pendingForm = f;
        document.getElementById('confirmModalText').textContent = f.dataset.confirm;
        modal.show();
      }
    });
    document.getElementById('confirmModalOk').addEventListener('click', function () {
      if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.submit(); }
    });
  }

  // Autocomplétion d'animaux : <input data-autocomplete data-target="#hidden" data-sexe="F" data-espece="#espece_id">
  document.querySelectorAll('input[data-autocomplete]').forEach(function (input) {
    const hidden = document.querySelector(input.dataset.target);
    const wrap = input.parentElement;
    wrap.style.position = 'relative';
    const list = document.createElement('div');
    list.className = 'list-group autocomplete-list shadow-sm d-none';
    wrap.appendChild(list);
    let timer = null;
    input.addEventListener('input', function () {
      if (hidden) hidden.value = '';
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 1) { list.classList.add('d-none'); return; }
      timer = setTimeout(function () {
        const params = new URLSearchParams({ r: 'animaux/recherche', q: q });
        if (input.dataset.sexe) params.set('sexe', input.dataset.sexe);
        if (input.dataset.espece) {
          const esp = document.querySelector(input.dataset.espece);
          if (esp && esp.value) params.set('espece_id', esp.value);
        }
        fetch(window.APP.baseUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
          .then(r => r.json())
          .then(function (data) {
            list.innerHTML = '';
            (data.results || []).forEach(function (a) {
              const b = document.createElement('button');
              b.type = 'button';
              b.className = 'list-group-item list-group-item-action py-1';
              b.textContent = a.numero_identification + (a.nom ? ' — ' + a.nom : '') + ' (' + a.sexe + ', ' + a.statut + ')';
              b.addEventListener('click', function () {
                input.value = a.numero_identification;
                if (hidden) hidden.value = a.id;
                list.classList.add('d-none');
                input.dispatchEvent(new Event('change'));
              });
              list.appendChild(b);
            });
            list.classList.toggle('d-none', !(data.results || []).length);
          });
      }, 200);
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) list.classList.add('d-none'); });
  });

  // Filtrage dynamique des listes dépendantes (races / catégories selon l'espèce, catégories selon le sexe)
  document.querySelectorAll('select[data-depends]').forEach(function (sel) {
    const parents = sel.dataset.depends.split(',').map(s => document.querySelector(s.trim()));
    const apply = function () {
      sel.querySelectorAll('option[data-espece], option[data-sexe]').forEach(function (o) {
        let ok = true;
        parents.forEach(function (p) {
          if (!p || !p.value) return;
          if (p.name === 'espece_id' && o.dataset.espece && o.dataset.espece !== p.value) ok = false;
          if (p.name === 'sexe' && o.dataset.sexe && o.dataset.sexe !== p.value) ok = false;
        });
        o.hidden = !ok;
        if (!ok && o.selected) sel.value = '';
      });
    };
    parents.forEach(p => p && p.addEventListener('change', apply));
    apply();
  });

  // Ouvre l'onglet demandé (?onglet=xxx)
  const onglet = new URLSearchParams(location.search).get('onglet');
  if (onglet) {
    const t = document.querySelector('[data-bs-target="#tab-' + onglet + '"]');
    if (t) bootstrap.Tab.getOrCreateInstance(t).show();
  }
})();
