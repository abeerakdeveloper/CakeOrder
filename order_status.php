<?php
require_once 'db.php';
$pageTitle = 'Check Order Status';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Check Order Status</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

        <div class="data-card" style="max-width:900px;margin:0 auto;">
            <h4>🔍 Check Order Status for Customer Inquiries</h4>
            <p style="color:#666;font-size:13px;margin-top:4px;">
                Search by Bill #, Customer Name, or Phone Number to provide updates to the client.
            </p>
            
            <div style="display:flex;gap:10px;margin-top:16px;">
                <input type="text" id="searchInput" 
                       placeholder="Enter Bill #, Customer Name or Phone Number..." 
                       style="flex:1;padding:14px;border:2px solid #6c3483;border-radius:8px;font-size:16px;"
                       autofocus
                       onkeyup="if(event.key=='Enter') searchOrders()">
                <button class="btn btn-primary" onclick="searchOrders()" style="padding:14px 28px;font-size:16px;">
                    🔍 Search
                </button>
            </div>
            
            <div id="searchResults" style="margin-top:24px;">
                <div style="text-align:center;padding:40px;color:#999;">
                    <div style="font-size:48px;margin-bottom:12px;">🔎</div>
                    <p>Enter a search term above to find orders</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function searchOrders() {
    var q = document.getElementById('searchInput').value.trim();
    if (!q) { alert('Please enter a search term'); return; }
    
    var result = document.getElementById('searchResults');
    result.innerHTML = '<div style="text-align:center;padding:40px;">⏳ Searching...</div>';
    
    fetch('order_status_api.php?q=' + encodeURIComponent(q))
    .then(function(r){return r.json();})
    .then(function(res) {
        if (res.found && res.orders.length > 0) {
            var html = '<h4 style="color:#6c3483;margin-bottom:12px;">Found '+res.orders.length+' order(s):</h4>';
            for (var i=0; i<res.orders.length; i++) {
                var o = res.orders[i];
                html += '<div style="background:#f5f0fa;padding:16px;border-radius:10px;margin-bottom:12px;border-left:5px solid #6c3483;">';
                html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">';
                html += '<div>';
                html += '<h3 style="color:#6c3483;margin-bottom:4px;">Bill #'+o.bill_no+'</h3>';
                html += '<div style="font-size:14px;color:#333;"><strong>'+o.party_detail+'</strong></div>';
                html += '<div style="font-size:13px;color:#666;">📱 '+o.cell_no+'</div>';
                html += '</div>';
                html += '<div style="text-align:right;">'+o.status_badge+'<br>';
                html += '<small style="color:#888;">🚚 '+o.deliver_date+' '+o.delivery_time+'</small></div>';
                html += '</div>';
                html += '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #ddd;">';
                html += '<div style="font-size:13px;"><strong>📦 Items:</strong> '+o.items+'</div>';
                html += '<div style="display:flex;gap:20px;margin-top:8px;font-size:13px;">';
                html += '<div>💰 <strong>Total:</strong> Rs. '+o.total+'</div>';
                html += '<div>✅ <strong>Paid:</strong> Rs. '+o.paid+'</div>';
                html += '<div style="color:#e74c3c;">⚠ <strong>Balance:</strong> Rs. '+o.balance+'</div>';
                html += '</div>';
                html += '</div>';
                html += '<div style="margin-top:12px;"><a href="order_detail.php?bill='+o.bill_no+'" class="btn btn-info">👁 View Full Details</a></div>';
                html += '</div>';
            }
            result.innerHTML = html;
        } else {
            result.innerHTML = '<div style="background:#fee;color:#c0392b;padding:20px;border-radius:8px;text-align:center;">❌ No orders found matching "<strong>'+q+'</strong>"</div>';
        }
    });
}
</script>
</body>
</html>