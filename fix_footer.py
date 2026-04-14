from pathlib import Path

path = Path('includes/footer_scripts.php')
text = path.read_text()
marker = "?>\r\n\r\n<!-- \xc3\xa2\xc5\x93\xe2\x80\x99 jQuery (load ONCE only) -->"
if "window.BASE_URL" not in text:
    injection = "?>\r\n\r\n<script>\r\n  window.BASE_URL = <?= json_encode((string)BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;\r\n</script>\r\n\r\n<!-- \xc3\xa2\xc5\x93\xe2\x80\x99 jQuery (load ONCE only) -->"
    text = text.replace(marker, injection)

replacements = {
    "/city_enro/assets/libs/jquery/dist/jquery.min.js": "<?= url_with_base('assets/libs/jquery/dist/jquery.min.js') ?>",
    "/city_enro/assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js": "<?= url_with_base('assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js') ?>",
    "/city_enro/assets/extra-libs/sparkline/sparkline.js": "<?= url_with_base('assets/extra-libs/sparkline/sparkline.js') ?>",
    "/city_enro/dist/js/app.min.js": "<?= url_with_base('dist/js/app.min.js') ?>",
    "/city_enro/dist/js/app-style-switcher.js": "<?= url_with_base('dist/js/app-style-switcher.js') ?>",
    "/city_enro/dist/js/waves.js": "<?= url_with_base('dist/js/waves.js') ?>",
    "/city_enro/dist/js/sidebarmenu.js": "<?= url_with_base('dist/js/sidebarmenu.js') ?>",
    "/city_enro/dist/js/custom.js": "<?= url_with_base('dist/js/custom.js') ?>",
    "/city_enro/dist/js/enterprise.js": "<?= url_with_base('dist/js/enterprise.js') ?>",
    "/city_enro/assets/js/enro-ui.js": "<?= url_with_base('assets/js/enro-ui.js') ?>",
}
for old, new in replacements.items():
    text = text.replace(f'src=\"{old}\"', f'src=\"{new}\"')

path.write_text(text)
