(function ($) {
  'use strict';

  function initGalleryMetabox() {
    var $root = $('#team-gallery-metabox-root');
    if (!$root.length) return;

    var selected = [];
    try {
      selected = JSON.parse($root.attr('data-selected') || '[]') || [];
    } catch (e) {
      selected = [];
    }

    var inputName = $root.data('input-name') || '_team_gallery_ids';

    var $list = $('<ul class="team-gallery-list" style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0;padding:0;list-style:none;"></ul>');
    var $hidden = $('<input type="hidden" />').attr('name', inputName);
    var $button = $('<button type="button" class="button"></button>').text(TeamMgmtL10n && TeamMgmtL10n.selectMedia ? TeamMgmtL10n.selectMedia : 'Select Media');

    function renderList() {
      $list.empty();
      if (!selected || !selected.length) {
        $hidden.val('');
        return;
      }
      $hidden.val(selected.join(','));
      selected.forEach(function (id) {
        var attachment = wp.media.attachment(id);
        var thumbUrl = attachment && attachment.attributes && (attachment.get('sizes') && attachment.get('sizes').thumbnail ? attachment.get('sizes').thumbnail.url : attachment.get('url'));
        var $li = $('<li style="width:80px;position:relative;"></li>');
        var $img = $('<img style="width:80px;height:80px;object-fit:cover;border:1px solid #ccd0d4;background:#fff;" />');
        if (thumbUrl) $img.attr('src', thumbUrl);
        var $remove = $('<a href="#" style="position:absolute;top:2px;right:2px;background:#dc3232;color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-weight:bold;">×</a>');
        $remove.on('click', function (e) {
          e.preventDefault();
          selected = selected.filter(function (x) { return x !== id; });
          renderList();
        });
        $li.append($img).append($remove);
        $list.append($li);
      });
    }

    var frame;
    $button.on('click', function (e) {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: TeamMgmtL10n && TeamMgmtL10n.editSelection ? TeamMgmtL10n.editSelection : 'Edit Selection',
        button: { text: TeamMgmtL10n && TeamMgmtL10n.selectMedia ? TeamMgmtL10n.selectMedia : 'Select Media' },
        multiple: true,
      });
      // Pre-select current
      frame.on('open', function () {
        var selection = frame.state().get('selection');
        (selected || []).forEach(function (id) {
          var att = wp.media.attachment(id);
          att.fetch();
          selection.add(att ? [att] : []);
        });
      });
      frame.on('select', function () {
        var items = frame.state().get('selection').toJSON();
        selected = items.map(function (m) { return parseInt(m.id, 10); });
        renderList();
      });
      frame.open();
    });

    $root.empty().append($button, $list, $hidden);
    renderList();
  }

  function initPeopleMetabox() {
    var $root = $('#team-people-metabox-root');
    if (!$root.length) return;

    var selected = [];
    try {
      selected = JSON.parse($root.attr('data-selected') || '[]') || [];
    } catch (e) {
      selected = [];
    }
    var inputName = $root.data('input-name') || '_team_people_ids';

    var $wrap = $('<div class="team-people-wrap"></div>');
    var $controls = $('<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;"></div>');
    var $search = $('<input type="search" class="regular-text" style="max-width:320px;" />')
      .attr('placeholder', (window.TeamMgmtL10n && TeamMgmtL10n.people && TeamMgmtL10n.people.searchPlaceholder) || 'Search people…');
    var $results = $('<div class="team-people-results" style="border:1px solid #ccd0d4;background:#fff;max-height:180px;overflow:auto;display:none;margin-top:6px;"></div>');
    var $list = $('<ul class="team-people-selected" style="margin:8px 0;padding:0;list-style:none;"></ul>');
    var $hidden = $('<input type="hidden" />').attr('name', inputName);

    function refreshHidden() {
      $hidden.val((selected || []).join(','));
    }

    function renderSelected() {
      $list.empty();
      if (!selected.length) {
        refreshHidden();
        return;
      }
      refreshHidden();
      selected.forEach(function (id, idx) {
        var $li = $('<li style="display:flex;align-items:center;gap:8px;margin:0 0 6px;padding:6px;border:1px solid #ccd0d4;background:#fff;"></li>');
        var $label = $('<span style="flex:1 1 auto;"></span>').text('#' + id);
        var $btnUp = $('<button type="button" class="button button-small" title="Move up">▲</button>');
        var $btnDown = $('<button type="button" class="button button-small" title="Move down">▼</button>');
        var $remove = $('<button type="button" class="button button-small" title="Remove">×</button>');

        $btnUp.on('click', function () {
          if (idx > 0) {
            var tmp = selected[idx - 1];
            selected[idx - 1] = selected[idx];
            selected[idx] = tmp;
            renderSelected();
          }
        });
        $btnDown.on('click', function () {
          if (idx < selected.length - 1) {
            var tmp = selected[idx + 1];
            selected[idx + 1] = selected[idx];
            selected[idx] = tmp;
            renderSelected();
          }
        });
        $remove.on('click', function () {
          selected = selected.filter(function (x) { return x !== id; });
          renderSelected();
        });

        // Fetch and show the person title for nicer UX
        fetchPerson(id).then(function (person) {
          if (person && person.title && person.title.rendered) {
            $label.text(person.title.rendered);
          }
        }).catch(function () {});

        $li.append($label, $btnUp, $btnDown, $remove);
        $list.append($li);
      });
    }

    function apiRoot() {
      if (window.wpApiSettings && wpApiSettings.root) return wpApiSettings.root;
      return '/wp-json/';
    }

    function fetchPerson(id) {
      return new Promise(function (resolve, reject) {
        $.getJSON(apiRoot() + 'wp/v2/person/' + id)
          .done(function (data) { resolve(data); })
          .fail(function (xhr) { reject(xhr); });
      });
    }

    function searchPeople(query) {
      return new Promise(function (resolve, reject) {
        $.getJSON(apiRoot() + 'wp/v2/person', { search: query, per_page: 20 })
          .done(function (data) { resolve(data || []); })
          .fail(function (xhr) { reject(xhr); });
      });
    }

    var debounceTimer = null;
    $search.on('input', function () {
      var q = ($search.val() || '').toString().trim();
      clearTimeout(debounceTimer);
      if (!q) { $results.hide().empty(); return; }
      debounceTimer = setTimeout(function () {
        searchPeople(q).then(function (items) {
          $results.empty();
          if (!items.length) {
            $results.append($('<div style="padding:8px;color:#666;"></div>').text((TeamMgmtL10n && TeamMgmtL10n.people && TeamMgmtL10n.people.noResults) || 'No people found.')).show();
            return;
          }
          items.forEach(function (p) {
            var id = parseInt(p.id, 10);
            var title = (p.title && p.title.rendered) ? p.title.rendered : ('#' + id);
            var $row = $('<div style="display:flex;gap:8px;align-items:center;padding:6px;border-bottom:1px solid #eee;"></div>');
            var $t = $('<div style="flex:1 1 auto;"></div>').text(title);
            var $add = $('<button type="button" class="button button-small"></button>').text((TeamMgmtL10n && TeamMgmtL10n.people && TeamMgmtL10n.people.add) || 'Add');
            $add.on('click', function () {
              if (selected.indexOf(id) === -1) {
                selected.push(id);
                renderSelected();
              }
            });
            $row.append($t, $add);
            $results.append($row);
          });
          $results.show();
        }).catch(function () {
          $results.hide().empty();
        });
      }, 250);
    });

    $controls.append($search);
    $wrap.append($controls, $results, $list, $hidden);
    $root.empty().append($wrap);
    renderSelected();
  }

  $(document).ready(function(){
    initGalleryMetabox();
    initPeopleMetabox();
  });
})(jQuery);
