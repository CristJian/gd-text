# GD Text Dynamic Poster (WordPress Plugin)

Plugin para crear posters dinámicos en WordPress usando `gd-text`.

## Funcionalidades

- Área de administración con listado de posters (CPT `gdtp_template`).
- Creación de plantillas por formato: `1:1`, `9:16`, `16:9` o personalizado.
- Fondo por color o imagen de la biblioteca de WordPress.
- Elementos por capas en JSON:
  - `text`: texto dinámico con placeholders `{user_name}`, `{email}`, `{date}`.
  - `photo`: foto del usuario (circular o rectangular).
  - `image`: imagen fija por `image_id`.
- Previsualización desde admin.
- Shortcode frontend: `[gd_text_poster id="123"]`.
- Generación de salida PNG.
- Generación PDF opcional si está disponible `dompdf/dompdf`.
- Botones de descargar y compartir.

## Ejemplo de elementos JSON

```json
[
  {
    "type": "text",
    "text": "Certificado para {user_name}",
    "x": 120,
    "y": 420,
    "width": 840,
    "height": 180,
    "font_size": 64,
    "color": "#ffffff",
    "align_x": "center",
    "align_y": "center"
  },
  {
    "type": "photo",
    "x": 420,
    "y": 130,
    "width": 240,
    "height": 240,
    "shape": "circle"
  }
]
```

## Instalación

1. Copia la carpeta `gd-text-poster` a `wp-content/plugins/`.
2. Activa el plugin en WordPress.
3. Crea un poster en **Posters Dinámicos**.
4. Inserta el shortcode en una página.

## Notas

- Requiere extensión PHP `gd` habilitada.
- Para PDF, instala Dompdf en WordPress y asegúrate de su autoload.
