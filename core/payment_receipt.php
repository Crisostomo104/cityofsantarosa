<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function generate_payment_receipt_pdf(array $receipt): string
{
    $escape = static fn ($value): string => htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $options = new Options();
    $options->setDefaultFont('DejaVu Sans');
    $options->setIsRemoteEnabled(false);

    $html = '<!doctype html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "DejaVu Sans", sans-serif; color: #253047; font-size: 12px; }
            .receipt { border: 1px solid #d9dee8; padding: 32px; }
            .brand { color: #8e1b1b; font-size: 12px; font-weight: bold; letter-spacing: 1px; }
            h1 { margin: 8px 0 4px; color: #8e1b1b; font-size: 23px; }
            .subheading { margin: 0 0 24px; color: #687386; }
            .receipt-number { margin: 0 0 22px; padding: 12px; background: #f8f3f3; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 10px 8px; border-bottom: 1px solid #e7eaf0; vertical-align: top; }
            td:first-child { width: 38%; color: #687386; }
            .amount { color: #8e1b1b; font-size: 18px; font-weight: bold; }
            .notice { margin-top: 24px; color: #687386; font-size: 10px; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="brand">CITY GOVERNMENT OF SANTA ROSA</div>
            <h1>Payment Acknowledgment</h1>
            <p class="subheading">eRequest Mo</p>
            <p class="receipt-number"><strong>Receipt No.:</strong> ' . $escape($receipt['receipt_number'] ?? '') . '</p>
            <table>
                <tr><td>Paid by</td><td>' . $escape($receipt['resident_name'] ?? '') . '</td></tr>
                <tr><td>Request No.</td><td>' . $escape($receipt['request_number'] ?? '') . '</td></tr>
                <tr><td>Document requested</td><td>' . $escape($receipt['document_type'] ?? '') . '</td></tr>
                <tr><td>Barangay</td><td>' . $escape($receipt['barangay'] ?? '') . '</td></tr>
                <tr><td>Date recorded</td><td>' . $escape($receipt['paid_at'] ?? '') . '</td></tr>
                <tr><td>Payment method</td><td>' . $escape($receipt['payment_method'] ?? '') . '</td></tr>
                <tr><td>Amount paid</td><td class="amount">PHP ' . $escape($receipt['amount'] ?? '0.00') . '</td></tr>
            </table>
            <p class="notice">This system-generated document acknowledges that the payment above was recorded in eRequest Mo. It is not an official BIR invoice or receipt.</p>
        </div>
    </body>
    </html>';

    $pdf = new Dompdf($options);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    return $pdf->output();
}
