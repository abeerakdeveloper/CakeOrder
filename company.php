<?php
// ============================================================
// Company details printed on invoices and receipts.
// Change the values here. No other file needs editing.
// The logo file must sit in the project root, beside db.php.
// ============================================================
$COMPANY = array(
    'name'         => 'Salman Bakers',
    'logo_file'    => 'clogo.png',
    'uan'          => '111-11-BAKERS (2253)',
    'website'      => 'www.salmanbakers.com',
    'facebook'     => 'facebook.com/salmanbakers',
    'branch_phone' => '',   // shown on the invoice when filled in, e.g. '091-xxxxxxx'
);

function company_logo_exists() {
    global $COMPANY;
    return file_exists(dirname(__FILE__) . '/' . $COMPANY['logo_file']);
}

// Logo image when clogo.png exists, otherwise the company name as plain text.
function company_logo_html($cssClass) {
    global $COMPANY;
    if (company_logo_exists()) {
        return '<img src="' . htmlspecialchars($COMPANY['logo_file']) . '" alt="' . htmlspecialchars($COMPANY['name']) . '" class="' . $cssClass . '">';
    }
    return '<span class="' . $cssClass . '-text">' . htmlspecialchars($COMPANY['name']) . '</span>';
}
