<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/billing_helpers.php';
$user = require_role('admin','receptionist');
$pdo = db();
$error = '';
$flash = (string)($_SESSION['sd_billing_flash'] ?? '');
unset($_SESSION['sd_billing_flash']);

function sd_bill_redirect(string $query=''): never { header('Location: payments.php'.$query); exit; }
function sd_bill_insert_item(PDO $pdo, int $invoiceId, string $type, string $description, ?int $appointmentId, ?int $inventoryId, float $qty, float $price): void {
    $stmt=$pdo->prepare('INSERT INTO invoice_items (invoice_id,item_type,description,appointment_id,inventory_item_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$invoiceId,$type,$description,$appointmentId,$inventoryId,$qty,$price,round($qty*$price,2)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) $error='Your security token expired. Refresh the page and try again.';
    else {
        $action=(string)($_POST['action'] ?? '');
        try {
            if ($action==='create_invoice') {
                $customerId=filter_var($_POST['customer_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $appointmentId=filter_var($_POST['appointment_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if ($appointmentId===false || $appointmentId===0) $appointmentId=null;
                $dueDate=trim((string)($_POST['due_date'] ?? date('Y-m-d',strtotime('+7 days'))));
                $tax=sd_bill_money($_POST['tax_amount'] ?? '0'); $discount=sd_bill_money($_POST['discount_amount'] ?? '0');
                $notes=trim((string)($_POST['notes'] ?? ''));
                if (!$customerId) throw new InvalidArgumentException('Choose a customer.');
                if (!sd_bill_valid_date($dueDate) || $dueDate<date('Y-m-d')) throw new InvalidArgumentException('Choose today or a future due date.');
                if ($tax===null || $tax<0 || $tax>1000000000 || $discount===null || $discount<0 || $discount>1000000000) throw new InvalidArgumentException('Tax and discount must be valid non-negative amounts in PKR.');
                if (strlen($notes)>1000) throw new InvalidArgumentException('Notes must be 1,000 characters or fewer.');
                $customerStmt=$pdo->prepare('SELECT id,name FROM customers WHERE id=?'); $customerStmt->execute([(int)$customerId]); $customer=$customerStmt->fetch();
                if (!$customer) throw new InvalidArgumentException('Customer not found.');
                $appointment=null;
                if ($appointmentId!==null) {
                    $a=$pdo->prepare("SELECT a.* FROM appointments a WHERE a.id=? AND a.status='completed'"); $a->execute([(int)$appointmentId]); $appointment=$a->fetch();
                    if (!$appointment) throw new InvalidArgumentException('Only a completed appointment can be added as a service invoice line.');
                    if ((int)$appointment['customer_id']!==(int)$customerId) throw new InvalidArgumentException('The selected appointment belongs to another customer.');
                    $existing=$pdo->prepare('SELECT id FROM invoices WHERE appointment_id=?'); $existing->execute([(int)$appointmentId]);
                    if ($existing->fetchColumn()) throw new InvalidArgumentException('This appointment already has an invoice. Open the existing invoice instead.');
                }
                $ids=is_array($_POST['product_item_id']??null)?$_POST['product_item_id']:[];
                $qtys=is_array($_POST['product_qty']??null)?$_POST['product_qty']:[];
                $prices=is_array($_POST['product_price']??null)?$_POST['product_price']:[];
                $products=[]; $needByItem=[]; $productSubtotal=0.0;
                for ($i=0;$i<min(12,count($ids));$i++) {
                    $rawId=trim((string)($ids[$i]??'')); if ($rawId==='') continue;
                    $itemId=filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    $qty=sd_bill_money($qtys[$i]??null); $price=sd_bill_money($prices[$i]??null);
                    if (!$itemId || $qty===null || $qty<=0 || $qty>100000 || $price===null || $price<0 || $price>1000000000) throw new InvalidArgumentException('Check each product line: choose a product, quantity above zero, and a valid selling price.');
                    $products[]=['id'=>(int)$itemId,'qty'=>(float)$qty,'price'=>(float)$price];
                    $needByItem[(int)$itemId]=($needByItem[(int)$itemId]??0)+(float)$qty;
                    $productSubtotal+=round((float)$qty*(float)$price,2);
                }
                if (!$appointment && !$products) throw new InvalidArgumentException('Add a completed appointment service or at least one product.');
                $subtotal=$productSubtotal + ($appointment ? (float)$appointment['service_price_snapshot'] : 0.0);
                if ($discount>$subtotal+$tax) throw new InvalidArgumentException('Discount cannot exceed the subtotal plus tax.');
                $total=round(max(0,$subtotal+$tax-$discount),2);

                $pdo->beginTransaction();
                // Lock each distinct stock item before invoice creation and make sure stock cannot go negative.
                $lockedItems=[]; ksort($needByItem);
                foreach ($needByItem as $itemId=>$required) {
                    $lock=$pdo->prepare('SELECT id,name,unit,quantity_on_hand,unit_cost FROM inventory_items WHERE id=? AND is_active=1 FOR UPDATE'); $lock->execute([(int)$itemId]); $item=$lock->fetch();
                    if (!$item) throw new InvalidArgumentException('A selected product is missing or inactive. Refresh the page and try again.');
                    if ((float)$item['quantity_on_hand']+0.00001 < (float)$required) throw new InvalidArgumentException('Not enough stock for '.$item['name'].'. Available: '.number_format((float)$item['quantity_on_hand'],3).' '.(string)$item['unit'].'.');
                    $lockedItems[(int)$itemId]=$item;
                }
                $tmp='TMP-'.bin2hex(random_bytes(8));
                $ins=$pdo->prepare("INSERT INTO invoices (invoice_number,customer_id,appointment_id,invoice_date,due_date,subtotal,tax_amount,discount_amount,total_amount,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,'open',?,?)");
                $ins->execute([$tmp,(int)$customerId,$appointmentId,date('Y-m-d'),$dueDate,round($subtotal,2),round($tax,2),round($discount,2),$total,$notes===''?null:$notes,(int)$user['id']]);
                $invoiceId=(int)$pdo->lastInsertId(); $invoiceNo='SD-'.date('Y').'-'.str_pad((string)$invoiceId,6,'0',STR_PAD_LEFT);
                $pdo->prepare('UPDATE invoices SET invoice_number=? WHERE id=?')->execute([$invoiceNo,$invoiceId]);
                if ($appointment) sd_bill_insert_item($pdo,$invoiceId,'service',(string)$appointment['service_name_snapshot'],(int)$appointment['id'],null,1.0,(float)$appointment['service_price_snapshot']);
                foreach ($products as $product) {
                    $item=$lockedItems[$product['id']];
                    sd_bill_insert_item($pdo,$invoiceId,'product',(string)$item['name'],null,(int)$item['id'],(float)$product['qty'],(float)$product['price']);
                }
                foreach ($needByItem as $itemId=>$required) {
                    $item=$lockedItems[(int)$itemId]; $before=(float)$item['quantity_on_hand']; $after=$before-(float)$required;
                    $pdo->prepare('UPDATE inventory_items SET quantity_on_hand=? WHERE id=?')->execute([$after,(int)$itemId]);
                    $note='Sold on invoice '.$invoiceNo;
                    $pdo->prepare("INSERT INTO inventory_movements (item_id,movement_type,quantity_delta,quantity_before,quantity_after,unit_cost_snapshot,notes,created_by) VALUES (?,'use',?,?,?,?,?,?)")
                        ->execute([(int)$itemId,-(float)$required,$before,$after,(float)$item['unit_cost'],$note,(int)$user['id']]);
                }
                $pdo->commit();
                $_SESSION['sd_billing_flash']='Invoice '.$invoiceNo.' created. Product stock was updated.';
                sd_bill_redirect('?view='.$invoiceId);
            } elseif ($action==='record_payment') {
                $invoiceId=filter_var($_POST['invoice_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $amount=sd_bill_money($_POST['amount']??null); $method=(string)($_POST['payment_method']??'cash');
                $reference=trim((string)($_POST['reference_number']??'')); $notes=trim((string)($_POST['payment_notes']??''));
                if (!$invoiceId || $amount===null || $amount<=0 || $amount>1000000000) throw new InvalidArgumentException('Enter a valid payment amount above zero.');
                if (!in_array($method,['cash','card','bank_transfer','mobile_wallet','other'],true)) throw new InvalidArgumentException('Choose a valid payment method.');
                if (strlen($reference)>120 || strlen($notes)>500) throw new InvalidArgumentException('Reference or payment notes are too long.');
                $pdo->beginTransaction(); $lock=$pdo->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE'); $lock->execute([(int)$invoiceId]); $invoice=$lock->fetch();
                if (!$invoice || $invoice['status']==='void') throw new InvalidArgumentException('Invoice not found or it has been voided.');
                $paidQ=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?'); $paidQ->execute([(int)$invoiceId]); $paid=(float)$paidQ->fetchColumn();
                $balance=max(0.0,(float)$invoice['total_amount']-$paid);
                if ($amount>$balance+0.005) throw new InvalidArgumentException('Payment exceeds the remaining balance of PKR '.number_format($balance,2).'.');
                $pdo->prepare('INSERT INTO payments (invoice_id,amount,payment_method,reference_number,notes,paid_at,received_by) VALUES (?,?,?,?,?,NOW(),?)')->execute([(int)$invoiceId,round($amount,2),$method,$reference===''?null:$reference,$notes===''?null:$notes,(int)$user['id']]);
                $newPaid=$paid+round($amount,2); $status=$newPaid+0.005 >= (float)$invoice['total_amount'] ? 'paid' : 'partially_paid';
                $pdo->prepare('UPDATE invoices SET status=? WHERE id=?')->execute([$status,(int)$invoiceId]);
                $pdo->commit();
                $_SESSION['sd_billing_flash']='Payment recorded successfully.';
                sd_bill_redirect('?view='.(int)$invoiceId);
            } else throw new InvalidArgumentException('Unknown billing action.');
        } catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('SalonDiva billing error: '.$e->getMessage()); $error='Could not save the invoice or payment. Confirm the Payment & Notifications migration was installed and refresh the page.'; }
    }
}

$viewId=filter_var($_GET['view']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if ($viewId) {
    try {
        $q=$pdo->prepare('SELECT i.*,c.name customer_name,c.email customer_email,c.phone customer_phone,u.name created_by_name FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN users u ON u.id=i.created_by WHERE i.id=?'); $q->execute([(int)$viewId]); $invoice=$q->fetch();
        if (!$invoice) { http_response_code(404); echo 'Invoice not found.'; exit; }
        $q=$pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id'); $q->execute([(int)$viewId]); $items=$q->fetchAll();
        $q=$pdo->prepare('SELECT p.*,u.name receiver_name FROM payments p LEFT JOIN users u ON u.id=p.received_by WHERE p.invoice_id=? ORDER BY p.paid_at,p.id'); $q->execute([(int)$viewId]); $payments=$q->fetchAll();
        $paid=array_sum(array_map(static fn($p)=>(float)$p['amount'],$payments)); $balance=max(0.0,(float)$invoice['total_amount']-$paid);
    } catch (Throwable $e) { error_log('SalonDiva invoice view error: '.$e->getMessage()); http_response_code(500); echo 'Invoice data is unavailable. Check the Payment & Notifications database migration.'; exit; }
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=sd_bill_h((string)$invoice['invoice_number'])?> · SalonDiva</title><style>
    :root{--ink:#321d2a;--pink:#c52f63;--bg:#fff6f9;--line:#ead8e1;--muted:#806c77;--green:#e3f5e8;--red:#fbe4e8}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,Segoe UI,sans-serif}.wrap{max-width:1000px;margin:24px auto;padding:0 14px}.card{background:#fffdf9;border:1px solid var(--line);border-radius:18px;padding:clamp(14px,3vw,28px);margin-bottom:16px}.brand{color:var(--pink);font-weight:850}.top{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}h1{margin:.25rem 0}h2{font-size:18px}.muted{color:var(--muted)}.nav{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.btn{background:var(--pink);color:#fff;border:0;border-radius:9px;padding:9px 12px;text-decoration:none;font-weight:700;cursor:pointer}.btn.light{background:#f5e5ec;color:var(--ink)}.tablewrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:520px}td,th{text-align:left;padding:10px;border-bottom:1px solid var(--line)}th{background:#fbf0f4}.totals{max-width:370px;margin-left:auto}.totals div{display:flex;justify-content:space-between;padding:5px}.field{display:flex;flex-direction:column;gap:5px;margin-bottom:10px}.field input,.field select,.field textarea{padding:9px;border:1px solid #dfcbd4;border-radius:8px;font:inherit}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.alert{background:var(--green);padding:10px;border-radius:9px;margin:10px 0}.alert.error{background:var(--red)}.pill{border-radius:99px;padding:4px 8px;background:#f5e5ec}@media print{body{background:white}.wrap{max-width:none;margin:0;padding:0}.card{border:0;box-shadow:none;padding:0;margin-bottom:14px}.no-print{display:none!important}}
    </style></head><body><main class="wrap"><section class="card no-print"><div class="nav"><a class="btn light" href="payments.php">← Payments & invoices</a><button class="btn" onclick="window.print()">Print invoice / receipt</button><a class="btn light" href="notifications-center.php">Notifications</a></div><?php if($flash!==''): ?><div class="alert"><?=sd_bill_h($flash)?></div><?php endif; ?><?php if($error!==''): ?><div class="alert error"><?=sd_bill_h($error)?></div><?php endif; ?></section>
    <section class="card"><div class="top"><div><div class="brand">SalonDiva</div><h1>Invoice / receipt</h1><p class="muted">Thank you for choosing SalonDiva.</p></div><div><h2><?=sd_bill_h((string)$invoice['invoice_number'])?></h2><div>Invoice date: <?=sd_bill_h((string)$invoice['invoice_date'])?></div><div>Due date: <?=sd_bill_h((string)$invoice['due_date'])?></div><div>Status: <span class="pill"><?=sd_bill_h(ucwords(str_replace('_',' ',(string)$invoice['status'])))?></span></div></div></div><hr style="border:0;border-top:1px solid var(--line);margin:18px 0"><div><strong>Bill to:</strong> <?=sd_bill_h((string)$invoice['customer_name'])?><br><?=sd_bill_h((string)($invoice['customer_email']??''))?><br><?=sd_bill_h((string)($invoice['customer_phone']??''))?></div><div class="tablewrap" style="margin-top:20px"><table><thead><tr><th>Description</th><th>Type</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=sd_bill_h((string)$item['description'])?></td><td><?=sd_bill_h(ucfirst((string)$item['item_type']))?></td><td><?=number_format((float)$item['quantity'],3)?></td><td>PKR <?=number_format((float)$item['unit_price'],2)?></td><td>PKR <?=number_format((float)$item['line_total'],2)?></td></tr><?php endforeach; ?></tbody></table></div><div class="totals"><div><span>Subtotal</span><strong>PKR <?=number_format((float)$invoice['subtotal'],2)?></strong></div><div><span>Tax</span><strong>PKR <?=number_format((float)$invoice['tax_amount'],2)?></strong></div><div><span>Discount</span><strong>− PKR <?=number_format((float)$invoice['discount_amount'],2)?></strong></div><div style="font-size:1.12rem;border-top:1px solid var(--line);margin-top:6px"><span>Total</span><strong>PKR <?=number_format((float)$invoice['total_amount'],2)?></strong></div><div><span>Paid</span><strong>PKR <?=number_format($paid,2)?></strong></div><div><span>Balance due</span><strong>PKR <?=number_format($balance,2)?></strong></div></div><?php if(!empty($invoice['notes'])): ?><p><strong>Notes:</strong> <?=nl2br(sd_bill_h((string)$invoice['notes']))?></p><?php endif; ?></section>
    <section class="card"><h2>Payment history</h2><div class="tablewrap"><table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Received by</th><th>Notes</th></tr></thead><tbody><?php foreach($payments as $p): ?><tr><td><?=sd_bill_h(date('d M Y H:i',strtotime((string)$p['paid_at'])))?></td><td><?=sd_bill_h(ucwords(str_replace('_',' ',(string)$p['payment_method'])))?></td><td><?=sd_bill_h((string)($p['reference_number']??''))?></td><td>PKR <?=number_format((float)$p['amount'],2)?></td><td><?=sd_bill_h((string)($p['receiver_name']??''))?></td><td><?=sd_bill_h((string)($p['notes']??''))?></td></tr><?php endforeach; ?><?php if(!$payments): ?><tr><td colspan="6">No payments recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
    <?php if($balance>0.005 && $invoice['status']!=='void'): ?><section class="card no-print"><h2>Record a payment</h2><p class="muted">Outstanding balance: <strong>PKR <?=number_format($balance,2)?></strong></p><form method="post"><input type="hidden" name="_csrf" value="<?=sd_bill_h(csrf_token())?>"><input type="hidden" name="action" value="record_payment"><input type="hidden" name="invoice_id" value="<?=(int)$invoice['id']?>"><div class="grid"><div class="field"><label>Payment amount (PKR) *</label><input name="amount" type="number" min="0.01" max="<?=sd_bill_h(number_format($balance,2,'.',''))?>" step="0.01" required></div><div class="field"><label>Payment method *</label><select name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="mobile_wallet">Mobile wallet</option><option value="other">Other</option></select></div><div class="field"><label>Reference number</label><input name="reference_number" maxlength="120" placeholder="Transaction ID (optional)"></div></div><div class="field"><label>Notes</label><textarea name="payment_notes" maxlength="500"></textarea></div><button class="btn" type="submit">Save payment</button></form></section><?php endif; ?></main></body></html><?php exit;
}

try {
    $customers=$pdo->query('SELECT id,name,email,phone FROM customers ORDER BY name')->fetchAll();
    $appointments=$pdo->query("SELECT a.id,a.booking_code,a.customer_id,a.starts_at,a.service_name_snapshot,a.service_price_snapshot,c.name customer_name FROM appointments a JOIN customers c ON c.id=a.customer_id LEFT JOIN invoices i ON i.appointment_id=a.id WHERE a.status='completed' AND i.id IS NULL ORDER BY a.starts_at DESC LIMIT 300")->fetchAll();
    $items=$pdo->query('SELECT id,name,unit,quantity_on_hand,unit_cost FROM inventory_items WHERE is_active=1 ORDER BY name')->fetchAll();
    $invoices=$pdo->query("SELECT i.*,c.name customer_name,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) paid_amount FROM invoices i JOIN customers c ON c.id=i.customer_id ORDER BY FIELD(i.status,'open','partially_paid','paid','void'),i.due_date DESC,i.id DESC LIMIT 150")->fetchAll();
} catch(Throwable $e) { error_log('SalonDiva billing lists error: '.$e->getMessage()); http_response_code(500); echo '<!doctype html><meta charset="utf-8"><h1>Payments setup is incomplete</h1><p>Run database/payments_notifications_migration.sql in the salondiva database after the Appointment Booking and Calendar & Analytics migrations, then refresh this page.</p>'; exit; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payments & Invoices · SalonDiva</title><style>
:root{--ink:#321d2a;--pink:#c52f63;--bg:#fff6f9;--line:#ead8e1;--muted:#806c77;--cream:#fffdf9;--green:#e3f5e8;--red:#fbe4e8;--gold:#fff1ce}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,Segoe UI,sans-serif}.wrap{max-width:1280px;margin:24px auto;padding:0 14px}.shell{background:var(--cream);border:1px solid var(--line);border-radius:20px;padding:clamp(14px,2.5vw,26px)}.brand{font-weight:850;color:var(--pink)}h1{margin:.25rem 0}h2{margin:0 0 12px;font-size:1.15rem}.muted,.hint{color:var(--muted)}.nav{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.btn{display:inline-block;background:var(--pink);color:#fff;padding:9px 12px;border-radius:9px;text-decoration:none;font-weight:700;border:0;cursor:pointer}.btn.light{background:#f5e5ec;color:var(--ink)}.panel{background:white;border:1px solid var(--line);border-radius:14px;padding:15px;margin-top:15px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field input,.field select,.field textarea{width:100%;padding:9px;border:1px solid #dfcbd4;border-radius:8px;font:inherit}.field textarea{min-height:65px}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:780px}td,th{text-align:left;padding:9px;border-bottom:1px solid var(--line);vertical-align:top}th{background:#fbf0f4}.alert{padding:11px;border-radius:9px;margin:12px 0;background:var(--green)}.alert.error{background:var(--red)}.pill{padding:3px 7px;border-radius:99px;background:#f5e5ec}.line{display:grid;grid-template-columns:minmax(180px,2fr) 100px 130px 42px;gap:8px;margin-bottom:8px;align-items:end}.line button{height:38px}.summary{font-weight:750;text-align:right;margin-top:10px}@media(max-width:700px){.line{grid-template-columns:1fr 1fr}.line .product{grid-column:1/-1}.line .remove{grid-column:2;justify-self:end}}
</style><script>
function addProductLine(){const box=document.getElementById('product-lines');if(box.children.length>=12){alert('Maximum 12 product lines per invoice.');return;}const row=document.createElement('div');row.className='line';row.innerHTML=document.getElementById('product-template').innerHTML;box.appendChild(row);}
function removeProductLine(btn){const box=document.getElementById('product-lines');if(box.children.length>1)btn.closest('.line').remove();else{const row=btn.closest('.line');row.querySelector('select').value='';row.querySelector('[name="product_qty[]"]').value='1';row.querySelector('[name="product_price[]"]').value='0.00';}}
function syncCustomer(){const appointment=document.getElementById('appointment_id');const opt=appointment.options[appointment.selectedIndex];if(opt&&opt.dataset.customer){document.getElementById('customer_id').value=opt.dataset.customer;}}
</script></head><body><main class="wrap"><div class="shell"><div class="brand">SalonDiva</div><h1>Payments & invoicing</h1><p class="muted">Create invoices for completed services and products, record partial or full payments, and print an invoice/receipt with payment history.</p><div class="nav"><a class="btn light" href="dashboard.php">Dashboard</a><a class="btn light" href="appointments.php">Appointments</a><a class="btn light" href="inventory.php">Inventory</a><a class="btn light" href="notifications-center.php">Notifications & reminders</a><a class="btn light" href="reports.php">Reports</a></div>
<?php if($flash!==''): ?><div class="alert"><?=sd_bill_h($flash)?></div><?php endif; ?><?php if($error!==''): ?><div class="alert error"><?=sd_bill_h($error)?></div><?php endif; ?>
<section class="panel"><h2>Create an invoice</h2><p class="hint">Select a completed appointment to add its service, and/or add sold products. Product quantities are deducted from inventory at invoice creation. Product selling prices are entered by staff and are not automatically set from cost price.</p><form method="post" id="invoice-form"><input type="hidden" name="_csrf" value="<?=sd_bill_h(csrf_token())?>"><input type="hidden" name="action" value="create_invoice"><div class="grid"><div class="field"><label>Customer *</label><select id="customer_id" name="customer_id" required><option value="">Choose customer…</option><?php foreach($customers as $c): ?><option value="<?=(int)$c['id']?>"><?=sd_bill_h((string)$c['name'])?><?=!empty($c['phone'])?' · '.sd_bill_h((string)$c['phone']):''?></option><?php endforeach; ?></select></div><div class="field"><label>Completed appointment service (optional)</label><select id="appointment_id" name="appointment_id" onchange="syncCustomer()"><option value="">No service line</option><?php foreach($appointments as $a): ?><option value="<?=(int)$a['id']?>" data-customer="<?=(int)$a['customer_id']?>"><?=sd_bill_h(date('d M Y H:i',strtotime((string)$a['starts_at'])))?> · <?=sd_bill_h((string)$a['customer_name'])?> · <?=sd_bill_h((string)$a['service_name_snapshot'])?> · <?=sd_bill_h((string)$a['booking_code'])?></option><?php endforeach; ?></select></div><div class="field"><label>Payment due date *</label><input type="date" name="due_date" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d',strtotime('+7 days'))?>" required></div></div><h3>Product lines</h3><div id="product-lines"><div class="line"><div class="field product"><label>Product (optional)</label><select name="product_item_id[]"><option value="">Choose product…</option><?php foreach($items as $item): ?><option value="<?=(int)$item['id']?>"><?=sd_bill_h((string)$item['name'])?> · stock <?=number_format((float)$item['quantity_on_hand'],3)?> <?=sd_bill_h((string)$item['unit'])?></option><?php endforeach; ?></select></div><div class="field"><label>Quantity</label><input name="product_qty[]" type="number" min="0.001" step="0.001" value="1"></div><div class="field"><label>Unit price (PKR)</label><input name="product_price[]" type="number" min="0" step="0.01" value="0.00"></div><button class="btn light remove" type="button" onclick="removeProductLine(this)" aria-label="Remove product">×</button></div></div><button class="btn light" type="button" onclick="addProductLine()">+ Add product line</button><div class="grid" style="margin-top:14px"><div class="field"><label>Tax amount (PKR)</label><input name="tax_amount" type="number" min="0" step="0.01" value="0.00"></div><div class="field"><label>Discount amount (PKR)</label><input name="discount_amount" type="number" min="0" step="0.01" value="0.00"></div></div><div class="field"><label>Invoice notes</label><textarea name="notes" maxlength="1000" placeholder="Optional notes for the receipt"></textarea></div><button class="btn" type="submit">Create invoice</button></form><template id="product-template"><div class="field product"><label>Product</label><select name="product_item_id[]"><option value="">Choose product…</option><?php foreach($items as $item): ?><option value="<?=(int)$item['id']?>"><?=sd_bill_h((string)$item['name'])?> · stock <?=number_format((float)$item['quantity_on_hand'],3)?> <?=sd_bill_h((string)$item['unit'])?></option><?php endforeach; ?></select></div><div class="field"><label>Quantity</label><input name="product_qty[]" type="number" min="0.001" step="0.001" value="1"></div><div class="field"><label>Unit price (PKR)</label><input name="product_price[]" type="number" min="0" step="0.01" value="0.00"></div><button class="btn light remove" type="button" onclick="removeProductLine(this)" aria-label="Remove product">×</button></template></section>
<section class="panel"><h2>Invoices</h2><p class="hint">Showing the most recent 150 invoices. Open an invoice to record payments and print a receipt.</p><div class="tablewrap"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Date / due date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($invoices as $i): $paidAmount=(float)$i['paid_amount']; $balance=max(0,(float)$i['total_amount']-$paidAmount); ?><tr><td><?=sd_bill_h((string)$i['invoice_number'])?></td><td><?=sd_bill_h((string)$i['customer_name'])?></td><td><?=sd_bill_h((string)$i['invoice_date'])?><br><span class="hint">Due <?=sd_bill_h((string)$i['due_date'])?></span></td><td>PKR <?=number_format((float)$i['total_amount'],2)?></td><td>PKR <?=number_format($paidAmount,2)?></td><td>PKR <?=number_format($balance,2)?></td><td><span class="pill"><?=sd_bill_h(ucwords(str_replace('_',' ',(string)$i['status'])))?></span></td><td><a class="btn light" href="payments.php?view=<?=(int)$i['id']?>">Open / receipt</a></td></tr><?php endforeach; ?><?php if(!$invoices): ?><tr><td colspan="8">No invoices have been created yet.</td></tr><?php endif; ?></tbody></table></div></section></div></main></body></html>
