<?php
// Define the invoice data and dynamic line items
$invoice = [
    'logo' => 'https://placeholder.com', 
    'invoice_no' => 'INV-2026-042',
    'date' => '2026-09-08',
    'due_date' => '2026-09-22',
    'client' => [
        'name' => 'Acme Corporation',
        'address' => '123 Innovation Way, Tech District'
    ],
    'company' => [
        'name' => 'PixelCraft Solutions LLC',
        'address' => '456 Digital Avenue, Suite 100',
        'email' => 'billing@pixelcraft.io'
    ],
    'items' => [
        ['desc' => 'Premium Cloud Hosting (Annual)', 'qty' => 1, 'price' => 240.00],
        ['desc' => 'Web Development & UI Design', 'qty' => 15, 'price' => 85.00],
        ['desc' => 'SEO Optimization Setup', 'qty' => 1, 'price' => 450.00],
    ]
];

// Calculate totals dynamically
$subtotal = 0;
foreach ($invoice['items'] as $item) {
    $subtotal += $item['qty'] * $item['price'];
}
$tax_rate = 0.10; // 10% Tax
$tax_amount = $subtotal * $tax_rate;
$grand_total = $subtotal + $tax_amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoice['invoice_no']; ?></title>
    <style>
        /* PDF and Browser Reset */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            font-size: 13px; 
            color: #2c3e50; 
            margin: 0; 
            padding: 15px;
            background-color: #ffffff;
        }
        
        /* Structural Slate-Blue Page Border Frame */
        .page-border {
            border: 3px solid #2b4c7e; /* Deep Slate Blue */
            padding: 35px;
            min-height: 980px; 
            box-sizing: border-box;
            background-color: #ffffff;
        }
        
        /* Layout Grid via PDF-Safe Tables */
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .layout-table td {
            vertical-align: top;
            border: none !important;
            padding: 0 !important;
        }
        
        /* Typography Elements */
        .invoice-title {
            color: #1a365d; /* Dark Navy Blue */
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0 0 10px 0;
        }
        
        .meta-text {
            font-size: 13px;
            color: #5a738e;
            line-height: 1.6;
        }
        
        .section-heading {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a6984;
            margin-bottom: 5px;
            font-weight: bold;
        }

        /* Highly Styled Product Line-Item Table */
        .invoice-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            margin-bottom: 30px;
        }
        .invoice-table th { 
            background-color: #2b4c7e; /* Vibrant Rich Slate Blue */
            color: #ffffff; 
            padding: 12px 14px; 
            text-align: left; 
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }
        .invoice-table td { 
            padding: 14px; 
            border-bottom: 1px solid #e1e8ed; 
            color: #2c3e50;
        }
        /* Zebra striping line items using a subtle blue tint */
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f4f7fa; 
        }
        .text-right { text-align: right; }
        
        /* Dynamic Summary / Totals block */
        .totals-table { 
            width: 320px; 
            border-collapse: collapse; 
            margin-top: 10px;
        }
        .totals-table td { 
            padding: 8px 14px; 
            color: #5a738e;
        }
        .grand-total-row { 
            background-color: #e6eef8; /* Pale Ice Blue Accent */
            font-weight: bold; 
        }
        .grand-total-row td {
            color: #1a365d !important;
            font-size: 15px;
            border-top: 2px solid #2b4c7e !important;
            padding: 12px 14px;
        }

        /* Native Browser Print Optimization Handler */
        @media print {
            body { padding: 0; }
            .page-border { height: 96vh; min-height: auto; }
            @page { size: auto; margin: 0mm; }
        }
    </style>
</head>
<body>

    <div class="page-border">
        
        <!-- Header Grid: Company Branding & Invoice Metadata -->
        <table class="layout-table">
            <tr>
                <td>
                    <img src="<?php echo $invoice['logo']; ?>" alt="Company Logo" style="max-width: 180px; height: auto;">
                    <div class="meta-text" style="margin-top: 15px;">
                        <strong><?php echo $invoice['company']['name']; ?></strong><br>
                        <?php echo $invoice['company']['address']; ?><br>
                        <?php echo $invoice['company']['email']; ?>
                    </div>
                </td>
                <td class="text-right">
                    <h1 class="invoice-title">INVOICE</h1>
                    <div class="meta-text">
                        <strong>Invoice Number:</strong> <?php echo $invoice['invoice_no']; ?><br>
                        <strong>Issue Date:</strong> <?php echo $invoice['date']; ?><br>
                        <strong style="color: #c0392b;">Due Date:</strong> <?php echo $invoice['due_date']; ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Details Grid: Bill To Metadata -->
        <table class="layout-table" style="margin-bottom: 40px;">
            <tr>
                <td style="background-color: #f8fafc; padding: 18px !important; border-left: 4px solid #2b4c7e !important; border-radius: 4px;">
                    <div class="section-heading">Billed To</div>
                    <strong style="font-size: 15px; color: #1a365d;"><?php echo htmlspecialchars($invoice['client']['name']); ?></strong>
                    <div class="meta-text" style="margin-top: 5px;">
                        <?php echo htmlspecialchars($invoice['client']['address']); ?>
                    </div>
                </td>
                <td></td><!-- Spacer cell for clean layout width alignment -->
            </tr>
        </table>

        <!-- Line Item Loop Frame -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right" style="width: 70px;">Qty</th>
                    <th class="text-right" style="width: 110px;">Unit Price</th>
                    <th class="text-right" style="width: 120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice['items'] as $item): ?>
                    <?php $line_total = $item['qty'] * $item['price']; ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($item['desc']); ?></td>
                        <td class="text-right" style="color: #5a738e;"><?php echo $item['qty']; ?></td>
                        <td class="text-right" style="color: #5a738e;">$<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-right" style="font-weight: 600; color: #1a365d;">$<?php echo number_format($line_total, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Calculation Bottom Blocks -->
        <table class="layout-table">
            <tr>
                <td><!-- Left empty layout block for alignment balance --></td>
                <td class="text-right" style="width: 320px;">
                    <table class="totals-table" style="float: right;">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-right" style="font-weight: 600;">$<?php echo number_format($subtotal, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Tax (10%)</td>
                            <td class="text-right" style="font-weight: 600;">$<?php echo number_format($tax_amount, 2); ?></td>
                        </tr>
                        <tr class="grand-total-row">
                            <td>Total Due</td>
                            <td class="text-right">$<?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
    </div>

</body>
</html>
