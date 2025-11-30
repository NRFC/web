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

  $(document).ready(initGalleryMetabox);
})(jQuery);
