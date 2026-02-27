jQuery(function($) {
  $('.gdtp-preview-button').on('click', function() {
    const $btn = $(this);
    const postId = $btn.data('post-id');
    const $wrap = $btn.closest('.gdtp-builder');
    const $img = $wrap.find('.gdtp-preview-image');

    $.post(GDTPAdmin.ajaxUrl, {
      action: 'gdtp_preview',
      nonce: GDTPAdmin.nonce,
      post_id: postId
    }).done(function(resp) {
      if (resp.success && resp.data.url) {
        $img.attr('src', resp.data.url + '&t=' + Date.now()).show();
      } else {
        alert(resp.data && resp.data.message ? resp.data.message : 'No se pudo generar preview');
      }
    }).fail(function() {
      alert('Error de comunicación con el servidor.');
    });
  });
});
