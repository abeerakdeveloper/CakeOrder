<!-- shared: square image zoom lightbox + AM/PM time helper (included once via header/footer) -->
<style>
.imgzoom-ovl { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,.78); display:none; align-items:center; justify-content:center; z-index:99999; padding:14px; }
.imgzoom-ovl.show { display:flex; }
.imgzoom-box { position:relative; background:#fff; border-radius:0; box-shadow:0 18px 50px rgba(0,0,0,.45); width:min(88vw, 720px, 86vh); height:min(88vw, 720px, 86vh); display:flex; align-items:center; justify-content:center; overflow:hidden; }
.imgzoom-box img { width:100%; height:100%; object-fit:contain; display:block; transition:transform .12s ease; }
.imgzoom-box img.zoomed { cursor:grab; }
.imgzoom-box img.dragging { cursor:grabbing; transition:none; }
.imgzoom-tools { position:absolute; top:8px; right:8px; display:flex; gap:6px; z-index:2; }
.imgzoom-tools button { height:38px; min-width:38px; border:1px solid #cbd5e1; border-radius:0; background:#fff; color:#0f172a; font-size:18px; font-weight:700; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.18); padding:0 8px; }
.imgzoom-tools button:hover { background:#f1f5f9; }
.imgzoom-tools .zlvl { min-width:56px; font-size:12px; font-weight:600; }
@media print { .imgzoom-ovl { display:none !important; } }
</style>
<div class="imgzoom-ovl" id="imgZoomOvl" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="imgzoom-box" id="imgZoomBox">
        <div class="imgzoom-tools" id="imgZoomTools">
            <button type="button" id="imgZoomOut" title="Zoom out">&minus;</button>
            <button type="button" class="zlvl" id="imgZoomLvl" title="Reset zoom">100%</button>
            <button type="button" id="imgZoomIn" title="Zoom in">+</button>
            <button type="button" id="imgZoomClose" title="Close">&times;</button>
        </div>
        <img id="imgZoomImg" src="" alt="">
    </div>
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
    var ovl = document.getElementById('imgZoomOvl');
    var img = document.getElementById('imgZoomImg');
    var lvl = document.getElementById('imgZoomLvl');
    var zoom = 1, panX = 0, panY = 0;

    function apply() {
        img.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
        lvl.textContent = Math.round(zoom * 100) + '%';
        img.classList.toggle('zoomed', zoom > 1);
    }
    function reset() { zoom = 1; panX = 0; panY = 0; apply(); }
    function setZoom(z) {
        zoom = Math.min(5, Math.max(1, Math.round(z * 100) / 100));
        if (zoom === 1) { panX = 0; panY = 0; }
        apply();
    }
    function closeZoom() { ovl.classList.remove('show'); }

    document.getElementById('imgZoomIn').addEventListener('click', function () { setZoom(zoom + 0.25); });
    document.getElementById('imgZoomOut').addEventListener('click', function () { setZoom(zoom - 0.25); });
    lvl.addEventListener('click', reset);
    document.getElementById('imgZoomClose').addEventListener('click', closeZoom);
    document.getElementById('imgZoomTools').addEventListener('click', function (e) { e.stopPropagation(); });

    // drag to pan when zoomed (mouse + touch), image only, never the page
    var dragging = false, sx = 0, sy = 0, bx = 0, by = 0;
    function dragStart(cx, cy) {
        if (zoom <= 1) return;
        dragging = true; sx = cx; sy = cy; bx = panX; by = panY;
        img.classList.add('dragging');
    }
    function dragMove(cx, cy) {
        if (!dragging) return;
        panX = bx + (cx - sx); panY = by + (cy - sy);
        apply();
    }
    function dragEnd() { dragging = false; img.classList.remove('dragging'); }
    img.addEventListener('mousedown', function (e) { dragStart(e.clientX, e.clientY); if (dragging) e.preventDefault(); });
    document.addEventListener('mousemove', function (e) { if (dragging) { dragMove(e.clientX, e.clientY); e.preventDefault(); } });
    document.addEventListener('mouseup', dragEnd);
    img.addEventListener('touchstart', function (e) { if (e.touches.length === 1) dragStart(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
    img.addEventListener('touchmove', function (e) { if (dragging && e.touches.length === 1) { dragMove(e.touches[0].clientX, e.touches[0].clientY); e.preventDefault(); } }, { passive: false });
    img.addEventListener('touchend', dragEnd);

    // delegated click: open zoom for cake thumbs / extra thumbs / receipt photo row
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.tagName) return;
        var el = (t.tagName === 'IMG') ? t : null;
        if (!el && t.closest) {
            var host = t.closest('.ph') || t.closest('a');
            if (host) el = host.querySelector('img');
        }
        if (!el) return;
        var src = el.getAttribute('src') || '';
        var full = '';
        if (src.indexOf('show_image.php') > -1 && src.indexOf('type=thumb') > -1) full = src.replace('type=thumb', 'type=image');
        else if (src.indexOf('show_image.php') > -1 && src.indexOf('type=extrathumb') > -1) full = src.replace('type=extrathumb', 'type=extra');
        else if (src.indexOf('data:image') === 0 && el.closest && el.closest('.photorow, .kd-imgrow')) full = src;
        if (!full) return;
        e.preventDefault();
        e.stopPropagation();
        img.src = full;
        reset();
        ovl.classList.add('show');
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeZoom();
        else if (e.key === '+' || e.key === '=') { if (ovl.classList.contains('show')) setZoom(zoom + 0.25); }
        else if (e.key === '-' || e.key === '_') { if (ovl.classList.contains('show')) setZoom(zoom - 0.25); }
    });
})();
</script>
