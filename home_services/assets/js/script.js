/* assets/js/script.js - small helpers used across the site */
document.addEventListener('DOMContentLoaded', function () {

  // 1. Ask before dangerous actions: <button data-confirm="Are you sure?">
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
  });

  // 2. Fade out success messages after a few seconds
  document.querySelectorAll('.alert-success').forEach(function (a) {
    setTimeout(function () {
      if (window.bootstrap) { bootstrap.Alert.getOrCreateInstance(a).close(); }
    }, 7000);
  });

  // 3. Registration: show worker-only fields when "Worker" is selected
  var roleRadios = document.querySelectorAll('input[name="role"]');
  var workerBox = document.getElementById('workerFields');
  if (roleRadios.length && workerBox) {
    var toggleWorker = function () {
      var isWorker = document.querySelector('input[name="role"]:checked').value === 'worker';
      workerBox.classList.toggle('d-none', !isWorker);
      workerBox.querySelectorAll('select, input').forEach(function (f) { f.required = isWorker; });
    };
    roleRadios.forEach(function (r) { r.addEventListener('change', toggleWorker); });
    toggleWorker();
  }

  // 4. Booking form: show the price and only list providers that match the chosen service
  var serviceSel = document.getElementById('service_id');
  var workerSel = document.getElementById('worker_id');
  var priceBox = document.getElementById('price_display');
  if (serviceSel && workerSel) {
    var refresh = function () {
      var opt = serviceSel.options[serviceSel.selectedIndex];
      var name = opt ? opt.getAttribute('data-name') : '';
      if (priceBox) { priceBox.value = opt && opt.value ? opt.getAttribute('data-price-label') : ''; }
      Array.prototype.forEach.call(workerSel.options, function (o) {
        if (!o.value) { return; }
        var match = o.getAttribute('data-service') === name;
        o.hidden = !match;
        o.disabled = !match;
      });
      var current = workerSel.options[workerSel.selectedIndex];
      if (current && current.value && current.disabled) { workerSel.value = ''; }
    };
    serviceSel.addEventListener('change', refresh);
    refresh();
  }
});
