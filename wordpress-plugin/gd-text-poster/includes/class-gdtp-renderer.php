<?php

use GDText\Box;
use GDText\Color;

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Renderer
{
    public function render($config, $data)
    {
        $width = (int) $config['width'];
        $height = (int) $config['height'];

        $im = imagecreatetruecolor($width, $height);
        if (!$im) {
            return new WP_Error('gdtp_gd_error', __('No se pudo crear el canvas.', 'gdtp'));
        }

        imagealphablending($im, true);
        imagesavealpha($im, true);

        $bgHex = !empty($config['background_color']) ? $config['background_color'] : '#000000';
        $bg = $this->hexToRgb($bgHex);
        $bgColor = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
        imagefill($im, 0, 0, $bgColor);

        if (!empty($config['background_image_id'])) {
            $bgPath = get_attached_file((int) $config['background_image_id']);
            if ($bgPath && file_exists($bgPath)) {
                $this->copyImageToCanvas($im, $bgPath, 0, 0, $width, $height);
            }
        }

        $fontFile = !empty($config['font_file']) && file_exists($config['font_file'])
            ? $config['font_file']
            : $this->findFallbackFont();

        if (!$fontFile) {
            return new WP_Error('gdtp_font_error', __('No se encontró una fuente TTF/OTF válida.', 'gdtp'));
        }

        if (!empty($config['elements']) && is_array($config['elements'])) {
            foreach ($config['elements'] as $element) {
                $type = isset($element['type']) ? $element['type'] : 'text';
                if ($type === 'text') {
                    $this->drawText($im, $element, $data, $fontFile);
                }

                if ($type === 'photo' && !empty($data['photo_path']) && file_exists($data['photo_path'])) {
                    $this->drawPhoto($im, $element, $data['photo_path']);
                }

                if ($type === 'image' && !empty($element['image_id'])) {
                    $img = get_attached_file((int) $element['image_id']);
                    if ($img && file_exists($img)) {
                        $this->drawImage($im, $element, $img);
                    }
                }
            }
        }

        $upload = wp_upload_dir();
        $folder = trailingslashit($upload['basedir']) . 'gd-posters';
        if (!file_exists($folder)) {
            wp_mkdir_p($folder);
        }

        $file = 'poster-' . wp_generate_password(10, false) . '.png';
        $path = trailingslashit($folder) . $file;
        imagepng($im, $path, 9, PNG_ALL_FILTERS);
        imagedestroy($im);

        return array(
            'path' => $path,
            'url' => trailingslashit($upload['baseurl']) . 'gd-posters/' . $file,
        );
    }

    public function build_pdf($pngPath)
    {
        if (!class_exists('Dompdf\\Dompdf')) {
            return new WP_Error('gdtp_pdf_lib', __('Dompdf no está instalado en WordPress.', 'gdtp'));
        }

        $upload = wp_upload_dir();
        $folder = trailingslashit($upload['basedir']) . 'gd-posters';
        if (!file_exists($folder)) {
            wp_mkdir_p($folder);
        }

        $pdfName = 'poster-' . wp_generate_password(10, false) . '.pdf';
        $pdfPath = trailingslashit($folder) . $pdfName;

        $imageData = base64_encode((string) file_get_contents($pngPath));
        $html = '<html><body style="margin:0"><img style="width:100%;" src="data:image/png;base64,' . $imageData . '" /></body></html>';

        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($pdfPath, $dompdf->output());

        return array(
            'path' => $pdfPath,
            'url' => trailingslashit($upload['baseurl']) . 'gd-posters/' . $pdfName,
        );
    }

    private function drawText($im, $element, $data, $fontFile)
    {
        $text = isset($element['text']) ? (string) $element['text'] : '';
        $text = strtr($text, array(
            '{user_name}' => isset($data['user_name']) ? $data['user_name'] : '',
            '{email}' => isset($data['email']) ? $data['email'] : '',
            '{date}' => isset($data['date']) ? $data['date'] : '',
        ));

        $box = new Box($im);
        $box->setFontFace($fontFile);
        $box->setFontSize(isset($element['font_size']) ? (int) $element['font_size'] : 48);

        $color = $this->hexToRgb(isset($element['color']) ? $element['color'] : '#ffffff');
        $box->setFontColor(new Color($color[0], $color[1], $color[2]));

        $box->setBox(
            isset($element['x']) ? (int) $element['x'] : 0,
            isset($element['y']) ? (int) $element['y'] : 0,
            isset($element['width']) ? (int) $element['width'] : imagesx($im),
            isset($element['height']) ? (int) $element['height'] : imagesy($im)
        );

        $alignX = isset($element['align_x']) ? $element['align_x'] : 'left';
        $alignY = isset($element['align_y']) ? $element['align_y'] : 'top';
        $box->setTextAlign($alignX, $alignY);

        if (!empty($element['line_height'])) {
            $box->setLineHeight((float) $element['line_height']);
        }

        if (!empty($element['shadow_color'])) {
            $shadow = $this->hexToRgb($element['shadow_color']);
            $box->setTextShadow(new Color($shadow[0], $shadow[1], $shadow[2], 50), 2, 2);
        }

        if (!empty($element['stroke_color']) && isset($element['stroke_size'])) {
            $stroke = $this->hexToRgb($element['stroke_color']);
            $box->setStrokeColor(new Color($stroke[0], $stroke[1], $stroke[2]));
            $box->setStrokeSize((int) $element['stroke_size']);
        }

        $box->draw($text);
    }

    private function drawPhoto($im, $element, $photoPath)
    {
        $x = isset($element['x']) ? (int) $element['x'] : 0;
        $y = isset($element['y']) ? (int) $element['y'] : 0;
        $w = isset($element['width']) ? (int) $element['width'] : 200;
        $h = isset($element['height']) ? (int) $element['height'] : 200;

        $tmp = imagecreatetruecolor($w, $h);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $transparent);

        $this->copyImageToCanvas($tmp, $photoPath, 0, 0, $w, $h);

        if (!empty($element['shape']) && $element['shape'] === 'circle') {
            $radius = min($w, $h) / 2;
            $cx = $w / 2;
            $cy = $h / 2;
            for ($py = 0; $py < $h; $py++) {
                for ($px = 0; $px < $w; $px++) {
                    $dx = $px - $cx;
                    $dy = $py - $cy;
                    if (($dx * $dx + $dy * $dy) > ($radius * $radius)) {
                        imagesetpixel($tmp, $px, $py, $transparent);
                    }
                }
            }
        }

        imagecopy($im, $tmp, $x, $y, 0, 0, $w, $h);
        imagedestroy($tmp);
    }

    private function drawImage($im, $element, $imagePath)
    {
        $x = isset($element['x']) ? (int) $element['x'] : 0;
        $y = isset($element['y']) ? (int) $element['y'] : 0;
        $w = isset($element['width']) ? (int) $element['width'] : 200;
        $h = isset($element['height']) ? (int) $element['height'] : 200;

        $this->copyImageToCanvas($im, $imagePath, $x, $y, $w, $h);
    }

    private function copyImageToCanvas($target, $sourcePath, $dstX, $dstY, $dstW, $dstH)
    {
        $info = @getimagesize($sourcePath);
        if (!$info) {
            return;
        }

        $source = null;
        if ($info[2] === IMAGETYPE_JPEG) {
            $source = @imagecreatefromjpeg($sourcePath);
        } elseif ($info[2] === IMAGETYPE_PNG) {
            $source = @imagecreatefrompng($sourcePath);
        } elseif ($info[2] === IMAGETYPE_GIF) {
            $source = @imagecreatefromgif($sourcePath);
        }

        if (!$source) {
            return;
        }

        imagecopyresampled(
            $target,
            $source,
            $dstX,
            $dstY,
            0,
            0,
            $dstW,
            $dstH,
            imagesx($source),
            imagesy($source)
        );

        imagedestroy($source);
    }

    private function hexToRgb($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return array(0, 0, 0);
        }

        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private function findFallbackFont()
    {
        $candidates = array(
            WP_CONTENT_DIR . '/uploads/fonts/OpenSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        );

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        return '';
    }
}
