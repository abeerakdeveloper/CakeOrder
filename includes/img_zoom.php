<!-- shared: square image zoom lightbox + AM/PM time helper (included once via header/footer) -->
<style>
.imgzoom-ovl { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,.78); display:none; align-items:center; justify-content:center; z-index:99999; padding:14px; }
.imgzoom-ovl.show { display:flex; }
.imgzoom-box { background:#fff; padding:10px; border-radius:8px; box-shadow:0 18px 50px rgba(0,0,0,.45); width:min(88vw, 720px, 86vh); height:min(88vw, 720px, 86vh); display:flex; align-items:center; justify-content:center; }
.imgzoom-box img { width:100%; height:100%; object-fit:contain; display:block; }
@media print { .imgzoom-ovl { display:none !important; } }
</style>
<div class="imgzoom-ovl" id="imgZoomOvl" onclick="this.classList.remove('show')">
    <div class="imgzoom-box"><img id="imgZoomImg" src="" alt=""></div>
</div>
<script>
function fmtTimeAMPM(t) {
    t = String(t == null ? '' : t).trim();
    if (!t) return '';
    var m = t.match(/^(\d{1,2}):(\d{2})\s*([AaPp]\.?[Mm]\.?)?$/);
    if (!m) return t;
    var h = parseInt(m[1], 10), mi = m[2];
    var ap = m[3] ? m[3].replace(/\./g, '').toUpperCase() : '';
    if (ap === 'PM' && h < 12) h += 12;
    if (ap === 'AM' && h === 12) h = 0;
    var suffix = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12; if (h12 === 0) h12 = 12;
    return h12 + ':' + mi + ' ' + suffix;
}
(function () {
    function zoomTo(src) {
        document.getElementById('imgZoomImg').src = src;
        document.getElementById('imgZoomOvl').classList.add('show');
    }
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.tagName) return;
        var img = (t.tagName === 'IMG') ? t : null;
        if (!img && t.closest) {
            var host = t.closest('.ph') || t.closest('a');
            if (host) img = host.querySelector('img');
        }
        if (!img) return;
        var src = img.getAttribute('src') || '';
        var full = '';
        if (src.indexOf('show_image.php') > -1 && src.indexOf('type=thumb') > -1) full = src.replace('type=thumb', 'type=image');
        else if (src.indexOf('show_image.php') > -1 && src.indexOf('type=extrathumb') > -1) full = src.replace('type=extrathumb', 'type=extra');
        else if (src.indexOf('data:image') === 0 && img.parentNode && String(img.parentNode.className || '').indexOf('photorow') > -1) full = src;
        if (!full) return;
        e.preventDefault();
        e.stopPropagation();
        zoomTo(full);
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { var z = document.getElementById('imgZoomOvl'); if (z) z.classList.remove('show'); }
    });
})();
</script>
