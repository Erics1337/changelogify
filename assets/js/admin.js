(function(){
  function onReady(fn){
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function initDateRangeToggle(){
    var select = document.getElementById('date_range_type');
    var customRow = document.getElementById('custom_date_range');
    if(!select || !customRow){ return; }

    function update(){
      customRow.style.display = (select.value === 'custom') ? 'table-row' : 'none';
    }

    update();
    select.addEventListener('change', update);
  }

  function initMappingUI(){
    var container = document.getElementById('event-mapping-container');
    var addBtn = document.getElementById('add-mapping-row');
    if(!container || !addBtn){ return; }

    var labels = (window.changelogifyAdmin && window.changelogifyAdmin.labels) ? window.changelogifyAdmin.labels : {};

    function createOption(value, text){
      var opt = document.createElement('option');
      opt.value = value;
      opt.textContent = text;
      return opt;
    }

    function addMappingRow(){
      var row = document.createElement('div');
      row.style.marginBottom = '10px';

      var input = document.createElement('input');
      input.type = 'text';
      input.name = 'changelogify_settings[event_mapping_keys][]';
      input.placeholder = 'Event action';
      input.className = 'regular-text';

      var arrow = document.createTextNode(' \u2192 ');

      var select = document.createElement('select');
      select.name = 'changelogify_settings[event_mapping_values][]';

      var options = ['added','changed','fixed','removed','security'];
      options.forEach(function(key){
        var label = labels[key] || (key.charAt(0).toUpperCase() + key.slice(1));
        select.appendChild(createOption(key, label));
      });

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'button mapping-remove';
      removeBtn.dataset.action = 'remove-mapping';
      removeBtn.textContent = labels.remove || 'Remove';

      row.appendChild(input);
      row.appendChild(arrow);
      row.appendChild(select);
      row.appendChild(removeBtn);
      container.appendChild(row);
    }

    addBtn.addEventListener('click', function(){
      addMappingRow();
    });

    container.addEventListener('click', function(e){
      var t = e.target;
      if(t && t.matches('button.mapping-remove')){
        var parent = t.parentElement;
        if(parent){ parent.remove(); }
      }
    });
  }

  onReady(function(){
    initDateRangeToggle();
    initMappingUI();
  });
})();
