jQuery(function($) {
  $('.gdtp-front').each(function() {
    const $root = $(this);
    const templateId = $root.data('template-id');

    $root.find('.gdtp-generate').on('click', function(e) {
      e.preventDefault();
      const formData = new FormData();
      formData.append('action', 'gdtp_generate');
      formData.append('nonce', GDTPFront.nonce);
      formData.append('template_id', templateId);
      formData.append('user_name', $root.find('[name="user_name"]').val());
      formData.append('email', $root.find('[name="email"]').val());
      const photoInput = $root.find('[name="photo"]')[0];
      if (photoInput && photoInput.files.length) {
        formData.append('photo', photoInput.files[0]);
      }

      const $status = $root.find('.gdtp-status');
      $status.text(GDTPFront.strings.generating);

      $.ajax({
        url: GDTPFront.ajaxUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false
      }).done(function(resp) {
        if (!resp.success) {
          $status.text((resp.data && resp.data.message) || GDTPFront.strings.error);
          return;
        }

        const png = resp.data.png;
        const pdf = resp.data.pdf || '';
        const $img = $root.find('.gdtp-result-image');
        $img.attr('src', png).show();

        const $download = $root.find('.gdtp-download');
        $download.show().off('click').on('click', function(ev) {
          ev.preventDefault();
          window.open(png, '_blank');
        });

        const $downloadPdf = $root.find('.gdtp-download-pdf');
        if (pdf) {
          $downloadPdf.show().off('click').on('click', function(ev) {
            ev.preventDefault();
            window.open(pdf, '_blank');
          });
        }

        const $share = $root.find('.gdtp-share');
        $share.show().off('click').on('click', async function(ev) {
          ev.preventDefault();
          if (navigator.share) {
            try {
              await navigator.share({
                title: 'Poster',
                text: 'Mira mi poster dinámico',
                url: png
              });
            } catch (err) {
              console.warn(err);
            }
          } else {
            window.prompt('Copia este enlace para compartir:', png);
          }
        });

        $status.text('Poster generado correctamente.');
      }).fail(function() {
        $status.text(GDTPFront.strings.error);
      });
    });
  });
});
