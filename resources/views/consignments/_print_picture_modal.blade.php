{{-- Asks whether the printed consignment should include a product Picture column.
     Any link with class "js-print-link" opens the PDF with or without ?images=1. --}}
<div id="printPictureModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; max-width:420px; width:92%; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.25);">
    <h5 class="mb-2"><i class="fas fa-image text-primary"></i> Show product pictures?</h5>
    <p class="text-muted mb-3">Add a <strong>Picture</strong> column with each item's photo to this print / export?</p>
    <div class="d-grid gap-2">
      <button type="button" id="printPicYes" class="btn btn-primary w-100 mb-2"><i class="fas fa-image"></i> Yes — with pictures</button>
      <button type="button" id="printPicNo" class="btn btn-outline-secondary w-100 mb-2">No — print as usual</button>
      <button type="button" id="printPicCancel" class="btn btn-link w-100">Cancel</button>
    </div>
  </div>
</div>

<script>
$(function () {
    var pendingPrintUrl = null;
    var pendingDownload = false;   // Excel links download in this tab instead of opening a new one
    var $modal = $('#printPictureModal');

    $(document).on('click', '.js-print-link', function (e) {
        e.preventDefault();
        pendingPrintUrl = $(this).attr('href');
        pendingDownload = !!$(this).data('download');
        $modal.css('display', 'flex');
    });

    function openPrint(withImages) {
        if (!pendingPrintUrl) return;
        var url = pendingPrintUrl + (withImages ? (pendingPrintUrl.indexOf('?') > -1 ? '&' : '?') + 'images=1' : '');
        $modal.hide();
        pendingPrintUrl = null;
        if (pendingDownload) { window.location.href = url; } else { window.open(url, '_blank'); }
    }

    $('#printPicYes').on('click', function () { openPrint(true); });
    $('#printPicNo').on('click',  function () { openPrint(false); });
    $('#printPicCancel').on('click', function () { $modal.hide(); pendingPrintUrl = null; });
});
</script>
